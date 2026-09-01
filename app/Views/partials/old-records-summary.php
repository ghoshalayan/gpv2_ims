<?php
/**
 * Compact "Old Records" panel shown on customer/supplier/salesman edit pages.
 *
 * Required vars (set by the parent view):
 *   $oldRecordsKind     string ('customer' | 'supplier' | 'salesman')
 *   $oldRecordsEntityId int
 *   $oldRecordsRollup   array {total_billed, total_paid, total_due, bill_count, unallocated}
 */
$kind     = $oldRecordsKind ?? '';
$entityId = (int) ($oldRecordsEntityId ?? 0);
$rollup   = $oldRecordsRollup ?? null;
if ($kind === '' || $entityId === 0 || $rollup === null) { return; }

$totalBilled = (float) ($rollup['total_billed'] ?? 0);
$totalDue    = (float) ($rollup['total_due']    ?? 0);
$unalloc     = (float) ($rollup['unallocated']  ?? 0);
$billCount   = (int)   ($rollup['bill_count']   ?? 0);

$hasAny = $billCount > 0 || $totalBilled > 0.001 || $unalloc > 0.001;
$borderClass = $totalDue > 0.001 ? 'border-warning' : ($hasAny ? 'border-info' : '');
?>
<div class="card shadow-sm mb-3 <?= $borderClass ?>">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>
            <i class="bi bi-archive"></i> Old Records
            <?php if ($billCount > 0): ?>
                <span class="badge text-bg-info ms-1"><?= $billCount ?> bill<?= $billCount === 1 ? '' : 's' ?></span>
            <?php else: ?>
                <span class="badge text-bg-light text-muted ms-1">none yet</span>
            <?php endif; ?>
        </strong>
        <a href="<?= url('/old-records/' . $kind . '/' . $entityId) ?>"
           class="btn btn-sm <?= $hasAny ? 'btn-outline-primary' : 'btn-outline-secondary' ?>">
            <i class="bi bi-pencil-square"></i>
            <?= $hasAny ? 'Manage old records' : 'Add old records' ?>
        </a>
    </div>
    <?php if ($hasAny): ?>
        <div class="card-body">
            <div class="row g-2 small">
                <div class="col-md-3">
                    <div class="text-muted">Total billed</div>
                    <div class="fw-bold">₹<?= number_format($totalBilled, 2) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Outstanding</div>
                    <div class="fw-bold <?= $totalDue > 0.001 ? 'text-warning' : 'text-muted' ?>">
                        ₹<?= number_format($totalDue, 2) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Unallocated</div>
                    <div class="fw-bold <?= $unalloc > 0.001 ? 'text-success' : 'text-muted' ?>">
                        ₹<?= number_format($unalloc, 2) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Carry-forward</div>
                    <div class="small">flows into this <?= e($kind) ?>'s passbook automatically</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card-body py-2 small text-muted">
            No pre-v2 bills or payments recorded for this <?= e($kind) ?>.
        </div>
    <?php endif; ?>
</div>
