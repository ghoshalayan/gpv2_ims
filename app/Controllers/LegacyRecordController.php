<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\LegacyObligationRepository;
use App\Repositories\LegacySettlementRepository;
use App\Services\LegacyRecordService;
use App\Support\Auth;
use App\Support\Flash;

/**
 * Old records (pre-v2 bills + settlements) attached to live customer / supplier
 * / salesman entities. Routes:
 *
 *   GET  /old-records                          → entities-with-old-data list (default kind=customer)
 *   GET  /old-records/{kind}/{entityId}        → per-entity show + add forms
 *   POST /old-records/{kind}/{entityId}/obligations  → add an old bill
 *   POST /old-records/{kind}/{entityId}/settlements  → record an old payment
 *   POST /old-records/obligations/{id}/delete  → admin only
 *   POST /old-records/settlements/{id}/delete  → admin only
 */
class LegacyRecordController
{
    public function __construct(
        private LegacyObligationRepository  $obligations = new LegacyObligationRepository(),
        private LegacySettlementRepository  $settlements = new LegacySettlementRepository(),
        private BankAccountRepository       $banks       = new BankAccountRepository(),
        private CashierDrawerRepository     $drawers     = new CashierDrawerRepository(),
        private LegacyRecordService         $service     = new LegacyRecordService(),
    ) {}

    public function index(): string
    {
        $p    = \App\Support\Paginator::fromQuery();
        $kind = $this->normalizeKind((string) ($_GET['kind'] ?? 'customer'));
        $result = $this->obligations->entitiesWithLegacyData($kind, $p->search, $p->page, $p->perPage);
        return view('legacy-records/index', [
            'title'      => $this->pageTitle($kind),
            'kind'       => $kind,
            'entities'   => $result['rows'],
            'pg'         => $p->buildContext($result['total'], url('/old-records'), ['kind' => $kind, 'q' => $p->search]),
        ]);
    }

    public function show(array $params): string
    {
        $kind = $this->normalizeKind((string) $params['kind']);
        $id   = (int) $params['id'];
        $entity = $this->service->resolveEntity($kind, $id);
        if ($entity === null) {
            Flash::error(\ucfirst($kind) . ' not found.');
            return redirect('/old-records?kind=' . $kind);
        }
        return view('legacy-records/show', [
            'title'        => $entity['name'],
            'kind'         => $kind,
            'entity'       => $entity,
            'obligations'  => $this->obligations->byEntity($kind, $id),
            'unpaidObligs' => $this->obligations->unpaidForEntity($kind, $id),
            'settlements'  => $this->settlements->byEntity($kind, $id),
            'rollup'       => $this->obligations->rollupForEntity($kind, $id),
            'banks'        => $this->banks->listActive(),
            'drawers'      => $this->drawers->listActive(),
        ]);
    }

    public function storeObligation(array $params): string
    {
        $kind = $this->normalizeKind((string) $params['kind']);
        $id   = (int) $params['id'];
        try {
            $this->service->createObligation($kind, $id, $_POST, (int) Auth::id());
            Flash::success('Recorded.');
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not record: ' . $e->getMessage());
        }
        return redirect("/old-records/{$kind}/{$id}");
    }

    public function deleteObligation(array $params): string
    {
        $obligationId = (int) $params['obligationId'];
        $row = $this->obligations->find($obligationId);
        if ($row === null) {
            Flash::error('Obligation not found.');
            return redirect('/old-records');
        }
        [$kind, $entityId] = $this->kindAndIdFromRow($row);
        try {
            $this->service->deleteObligation($obligationId, (int) Auth::id());
            Flash::success('Deleted.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        return redirect($kind && $entityId ? "/old-records/{$kind}/{$entityId}" : '/old-records');
    }

    public function storeSettlement(array $params): string
    {
        $kind = $this->normalizeKind((string) $params['kind']);
        $id   = (int) $params['id'];
        try {
            $this->service->recordSettlement($kind, $id, $_POST, (int) Auth::id());
            Flash::success('Settlement recorded.');
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not record: ' . $e->getMessage());
        }
        return redirect("/old-records/{$kind}/{$id}");
    }

    public function deleteSettlement(array $params): string
    {
        $settlementId = (int) $params['settlementId'];
        $row = $this->settlements->find($settlementId);
        if ($row === null) {
            Flash::error('Settlement not found.');
            return redirect('/old-records');
        }
        [$kind, $entityId] = $this->kindAndIdFromRow($row);
        try {
            $this->service->deleteSettlement($settlementId, (int) Auth::id());
            Flash::success('Settlement deleted. Account effect reversed.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        return redirect($kind && $entityId ? "/old-records/{$kind}/{$entityId}" : '/old-records');
    }

    /** @return array{0:?string, 1:?int} */
    private function kindAndIdFromRow(array $row): array
    {
        if (!empty($row['customer_id'])) return ['customer', (int) $row['customer_id']];
        if (!empty($row['supplier_id'])) return ['supplier', (int) $row['supplier_id']];
        if (!empty($row['salesman_id'])) return ['salesman', (int) $row['salesman_id']];
        return [null, null];
    }

    private function normalizeKind(string $raw): string
    {
        return \in_array($raw, LegacyRecordService::KINDS, true) ? $raw : 'customer';
    }

    private function pageTitle(string $kind): string
    {
        $word = match ($kind) {
            'supplier' => 'Suppliers',
            'salesman' => 'Salesmen',
            'customer' => 'Customers',
            default    => 'Records',
        };
        return "Old Records · {$word}";
    }
}
