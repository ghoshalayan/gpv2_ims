<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DueListRepository;
use App\Support\ActivityLog;
use App\Support\Flash;

/**
 * CR-3 — short-term / long-term tagging of dues.
 *
 * Kept separate from ReportsController so that controller stays entirely read-only,
 * and so the whole due-term feature (this file + DueListRepository's tag methods +
 * migration 0055) can be removed in one step if it is rolled back.
 *
 * Tagging is deliberately allowed on FINALIZED invoices: due_term is a non-financial
 * classification that never enters a total, tax figure, or balance. It is not part of
 * the invoice's accounting record, so finalize-immutability does not apply to it.
 */
class DueTermController
{
    private const VALID_TERMS = ['short', 'long'];

    public function __construct(
        private DueListRepository $repo = new DueListRepository(),
    ) {}

    /** POST /due-list/invoice/{id}/term — tag one invoice. */
    public function updateInvoice(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->repo->invoiceDueTerm($id);
        if ($invoice === null) {
            Flash::error('Invoice not found.');
            return redirect($this->backUrl());
        }

        $term = $this->resolveTerm();
        $this->repo->setInvoiceDueTerm($id, $term);

        ActivityLog::log('update', 'sales_invoice', $id, [
            'label'  => $invoice['invoice_number'],
            'before' => ['due_term' => $invoice['due_term']],
            'after'  => ['due_term' => $term],
            'notes'  => 'Due-term tag changed from the Due List.',
        ]);

        Flash::success(\sprintf(
            'Invoice %s tagged as %s.',
            $invoice['invoice_number'] !== '' ? $invoice['invoice_number'] : "#{$id}",
            $this->label($term)
        ));
        return redirect($this->backUrl());
    }

    /**
     * POST /due-list/customer/{id}/term — set the customer's default term. Applies to
     * every one of their invoices that carries no explicit tag of its own, and to their
     * legacy obligations.
     */
    public function updateCustomer(array $params): string
    {
        $id       = (int) $params['id'];
        $customer = $this->repo->customerDueTerm($id);
        if ($customer === null) {
            Flash::error('Customer not found.');
            return redirect($this->backUrl());
        }

        $term = $this->resolveTerm();
        $this->repo->setCustomerDefaultDueTerm($id, $term);

        ActivityLog::log('update', 'customer', $id, [
            'label'  => $customer['name'],
            'before' => ['default_due_term' => $customer['default_due_term']],
            'after'  => ['default_due_term' => $term],
            'notes'  => 'Default due-term changed from the Due List.',
        ]);

        Flash::success(\sprintf(
            "%s's default due term set to %s.",
            $customer['name'],
            $this->label($term)
        ));
        return redirect($this->backUrl());
    }

    /** Anything that is not an explicit 'short'/'long' clears the tag back to untagged. */
    private function resolveTerm(): ?string
    {
        $term = (string) ($_POST['due_term'] ?? '');
        return \in_array($term, self::VALID_TERMS, true) ? $term : null;
    }

    private function label(?string $term): string
    {
        return match ($term) {
            'short' => 'Short-term',
            'long'  => 'Long-term',
            default => 'Untagged',
        };
    }

    /**
     * Return to the due list preserving the tab/filter the user was on. Only the known
     * query keys are carried through, so the redirect target can't be steered by input.
     */
    private function backUrl(): string
    {
        $allowed = [];
        foreach (['tab', 'term', 'q'] as $key) {
            $value = \trim((string) ($_POST[$key] ?? ''));
            if ($value !== '') {
                $allowed[$key] = $value;
            }
        }
        return '/reports/due-list' . ($allowed === [] ? '' : '?' . \http_build_query($allowed));
    }
}
