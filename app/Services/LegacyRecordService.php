<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\LegacyObligationRepository;
use App\Repositories\LegacySettlementRepository;
use App\Repositories\SalesmanRepository;
use App\Repositories\SupplierRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Coordinator for legacy obligations + settlements. Each legacy record now
 * attaches directly to ONE live entity — a customer, supplier, or salesman
 * (single-entity model, post-0046).
 *
 * Sign convention by kind (decides cash direction at settlement time):
 *   customer  → settlement CREDITS the receiving account (we got money)
 *   supplier  → settlement DEBITS  the paying account   (we paid)
 *   salesman  → settlement DEBITS  the paying account   (we paid commission)
 *
 * Account effects:
 *   recordSettlement → +amount (customer) or -amount (supplier/salesman)
 *   deleteSettlement → exact reverse
 *   createObligation/delete → no account effect (reference data)
 */
class LegacyRecordService
{
    public const KINDS = ['customer', 'supplier', 'salesman'];

    public function __construct(
        private LegacyObligationRepository  $obligations = new LegacyObligationRepository(),
        private LegacySettlementRepository  $settlements = new LegacySettlementRepository(),
        private BankAccountRepository       $banks       = new BankAccountRepository(),
        private CashierDrawerRepository     $drawers     = new CashierDrawerRepository(),
        private CustomerRepository          $customers   = new CustomerRepository(),
        private SupplierRepository          $suppliers   = new SupplierRepository(),
        private SalesmanRepository          $salesmen    = new SalesmanRepository(),
    ) {}

    /* ---------- Entity resolution ---------- */

    /**
     * Returns the live entity row, or null if missing.
     * @return ?array<string, mixed>
     */
    public function resolveEntity(string $kind, int $entityId): ?array
    {
        return match ($kind) {
            'customer' => $this->customers->find($entityId),
            'supplier' => $this->suppliers->find($entityId),
            'salesman' => $this->salesmen->find($entityId),
            default    => null,
        };
    }

    /* ---------- Obligation ---------- */

    public function createObligation(string $kind, int $entityId, array $input, int $userId): int
    {
        $this->assertKind($kind);
        $entity = $this->resolveEntity($kind, $entityId);
        if ($entity === null) {
            throw new \RuntimeException(\ucfirst($kind) . " #{$entityId} not found.");
        }

        $number   = \trim((string) ($input['obligation_number'] ?? ''));
        $date     = \trim((string) ($input['obligation_date']   ?? \date('Y-m-d')));
        $amount   = \round((float) ($input['obligation_amount'] ?? 0), 2);
        $opening  = \round((float) ($input['opening_paid_amount'] ?? 0), 2);
        $remarks  = \trim((string) ($input['remarks'] ?? '')) ?: null;

        if ($number === '') {
            throw new \RuntimeException($this->obligationLabel($kind) . ' number is required.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \RuntimeException('Date must be YYYY-MM-DD.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }
        if ($opening < 0 || $opening > $amount + 0.01) {
            throw new \RuntimeException('Opening paid amount must be between 0 and the total amount.');
        }

        $due    = \round($amount - $opening, 2);
        $status = $due <= 0.0 ? 'paid' : ($opening > 0 ? 'partial' : 'unpaid');

        $newId = $this->obligations->insert(
            $this->withEntityIdColumn($kind, $entityId, [
                'obligation_number'   => $number,
                'obligation_date'     => $date,
                'obligation_amount'   => $amount,
                'opening_paid_amount' => $opening,
                'due_amount'          => \max(0.0, $due),
                'payment_status'      => $status,
                'remarks'             => $remarks,
            ]),
            $userId
        );

        ActivityLog::log('create', 'legacy_obligation', $newId, [
            'label' => $number,
            'notes' => "kind={$kind}, entity={$entity['name']}"
                     . ', amount=₹' . \number_format($amount, 2)
                     . ', opening_paid=₹' . \number_format($opening, 2),
        ]);
        return $newId;
    }

    public function deleteObligation(int $obligationId, int $userId): void
    {
        unset($userId);
        $row = $this->obligations->find($obligationId);
        if ($row === null) {
            throw new \RuntimeException('Legacy obligation not found.');
        }
        if ($this->obligations->hasAnySettlement($obligationId)) {
            throw new \RuntimeException(
                'Cannot delete: settlement(s) exist against this obligation. Delete the settlement(s) first.'
            );
        }
        $this->obligations->delete($obligationId);
        ActivityLog::log('delete', 'legacy_obligation', $obligationId, [
            'label' => $row['obligation_number'] ?? null,
            'notes' => 'amount=₹' . \number_format((float) $row['obligation_amount'], 2),
        ]);
    }

    /* ---------- Settlement ---------- */

    public function recordSettlement(string $kind, int $entityId, array $input, int $userId): int
    {
        $this->assertKind($kind);
        $entity = $this->resolveEntity($kind, $entityId);
        if ($entity === null) {
            throw new \RuntimeException(\ucfirst($kind) . " #{$entityId} not found.");
        }

        $date         = \trim((string) ($input['settlement_date'] ?? \date('Y-m-d')));
        $amount       = \round((float) ($input['amount'] ?? 0), 2);
        $obligationId = ((int) ($input['obligation_id'] ?? 0)) ?: null;
        $mode         = (string) ($input['payment_mode'] ?? 'cash');
        $reference    = \trim((string) ($input['payment_reference'] ?? '')) ?: null;
        $remarks      = \trim((string) ($input['remarks'] ?? '')) ?: null;

        [$accountKind, $accountId] = $this->parseAccountChoice((string) ($input['account'] ?? ''));

        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \RuntimeException('Settlement date must be YYYY-MM-DD.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Settlement amount must be greater than zero.');
        }
        if (!\in_array($mode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
            throw new \RuntimeException('Invalid payment mode.');
        }
        if ($accountKind === null || $accountId <= 0) {
            $verb = $kind === 'customer' ? 'receives' : 'pays';
            throw new \RuntimeException("Pick the bank or drawer that {$verb} this settlement.");
        }
        $accountRow = $this->resolveAccount($accountKind, $accountId);
        if ($accountRow === null) {
            throw new \RuntimeException('Account not found.');
        }
        if ($accountRow['status'] !== 'active') {
            throw new \RuntimeException("Account '{$accountRow['name']}' is inactive.");
        }

        $newId = 0;
        Db::transaction(function () use (
            $kind, $entityId, $obligationId, $date, $amount, $mode, $reference,
            $accountKind, $accountId, $remarks, $userId, &$newId
        ): void {
            if ($obligationId !== null) {
                $obligation = $this->obligations->findForUpdate($obligationId);
                if ($obligation === null) {
                    throw new \RuntimeException("Obligation #{$obligationId} not found.");
                }
                $col = $this->kindColumn($kind);
                if ((int) ($obligation[$col] ?? 0) !== $entityId) {
                    throw new \RuntimeException('Obligation belongs to a different entity.');
                }
                if ($amount > (float) $obligation['due_amount'] + 0.001) {
                    throw new \RuntimeException(\sprintf(
                        'Settlement ₹%0.2f exceeds remaining due ₹%0.2f on %s.',
                        $amount, $obligation['due_amount'], $obligation['obligation_number']
                    ));
                }
            }

            $newId = $this->settlements->insert(
                $this->withEntityIdColumn($kind, $entityId, [
                    'obligation_id'    => $obligationId,
                    'settlement_date'  => $date,
                    'amount'           => $amount,
                    'payment_mode'     => $mode,
                    'payment_reference'=> $reference,
                    'account_kind'     => $accountKind,
                    'account_id'       => $accountId,
                    'remarks'          => $remarks,
                ]),
                $userId
            );

            // Direction by kind: customer = +amount; supplier/salesman = -amount.
            $delta = $kind === 'customer' ? $amount : -$amount;
            $this->applyAccountDelta($accountKind, $accountId, $delta);

            if ($obligationId !== null) {
                $this->obligations->recompute($obligationId, $userId);
            }
            ActivityLog::log('create', 'legacy_settlement', $newId, [
                'label' => 'LS-' . $newId,
                'notes' => "kind={$kind}, entity_id={$entityId}"
                         . ', obligation_id=' . ($obligationId ?? 'unallocated')
                         . ', amount=₹' . \number_format($amount, 2)
                         . ', delta=' . ($delta > 0 ? '+' : '') . $delta,
            ]);
        });
        return $newId;
    }

    public function deleteSettlement(int $settlementId, int $userId): void
    {
        Db::transaction(function () use ($settlementId, $userId): void {
            $settlement = $this->settlements->find($settlementId);
            if ($settlement === null) {
                throw new \RuntimeException('Legacy settlement not found.');
            }
            $kind = $this->kindFromRow($settlement);
            if ($kind === null) {
                throw new \RuntimeException('Settlement has no entity attribution; cannot reverse.');
            }
            // Reverse: customer was +amount → debit; supplier/salesman was -amount → credit.
            $reverse = $kind === 'customer' ? -((float) $settlement['amount']) : (float) $settlement['amount'];
            $this->applyAccountDelta(
                (string) $settlement['account_kind'],
                (int) $settlement['account_id'],
                $reverse
            );
            $obligationId = $settlement['obligation_id'] !== null ? (int) $settlement['obligation_id'] : null;
            $this->settlements->delete($settlementId);
            if ($obligationId !== null) {
                $this->obligations->recompute($obligationId, $userId);
            }
            ActivityLog::log('delete', 'legacy_settlement', $settlementId, [
                'label' => 'LS-' . $settlementId,
                'notes' => 'kind=' . $kind . ', amount=₹' . \number_format((float) $settlement['amount'], 2),
            ]);
        });
    }

    /* ---------- helpers ---------- */

    private function assertKind(string $kind): void
    {
        if (!\in_array($kind, self::KINDS, true)) {
            throw new \RuntimeException("Unknown kind: {$kind}");
        }
    }

    private function obligationLabel(string $kind): string
    {
        return match ($kind) {
            'salesman' => 'Commission entry',
            default    => 'Bill',
        };
    }

    private function resolveAccount(string $kind, int $id): ?array
    {
        return $kind === 'bank_account' ? $this->banks->find($id) : $this->drawers->find($id);
    }

    private function applyAccountDelta(string $kind, int $id, float $delta): void
    {
        if ($kind === 'bank_account') {
            $this->banks->applyDelta($id, $delta);
        } else {
            $this->drawers->applyDelta($id, $delta);
        }
    }

    private function parseAccountChoice(string $raw): array
    {
        if ($raw === '' || !\str_contains($raw, ':')) {
            return [null, 0];
        }
        [$kind, $id] = \explode(':', $raw, 2);
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) {
            return [null, 0];
        }
        return [$kind, (int) $id];
    }

    private function kindColumn(string $kind): string
    {
        return match ($kind) {
            'customer' => 'customer_id',
            'supplier' => 'supplier_id',
            'salesman' => 'salesman_id',
        };
    }

    /** Drops the matching id into the right column, leaves the other two NULL. */
    private function withEntityIdColumn(string $kind, int $entityId, array $base): array
    {
        return $base + [
            'customer_id' => $kind === 'customer' ? $entityId : null,
            'supplier_id' => $kind === 'supplier' ? $entityId : null,
            'salesman_id' => $kind === 'salesman' ? $entityId : null,
        ];
    }

    /** Reverse-resolve kind from a row where exactly one of the id columns is set. */
    private function kindFromRow(array $row): ?string
    {
        if (!empty($row['customer_id'])) return 'customer';
        if (!empty($row['supplier_id'])) return 'supplier';
        if (!empty($row['salesman_id'])) return 'salesman';
        return null;
    }
}
