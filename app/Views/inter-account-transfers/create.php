<?php
/** @var array $banks */
/** @var array $drawers */
/** @var string $preFromKind */
/** @var int $preFromId */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-arrow-left-right"></i> New Transfer</h1>
    <a href="<?= url('/inter-account-transfers') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    Move funds between bank accounts and cashier drawers. Posting is atomic — source balance
    decrements and destination balance increments in a single transaction. Transfers are
    append-only in v1; to undo, post a reverse transfer.
</div>

<form method="post" action="<?= url('/inter-account-transfers') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">From <span class="text-danger">*</span></label>
                    <select name="from_id" class="form-select" id="from_id" required>
                        <option value="" data-kind="">— Select source —</option>
                        <optgroup label="Bank Accounts">
                            <?php foreach ($banks as $b): ?>
                                <?php $selected = $preFromKind === 'bank_account' && $preFromId === (int) $b['id']; ?>
                                <option value="<?= (int) $b['id'] ?>" data-kind="bank_account" data-balance="<?= e($b['current_balance']) ?>"
                                    <?= $selected ? 'selected' : '' ?>>
                                    🏦 <?= e($b['name']) ?> (₹<?= number_format((float) $b['current_balance'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Cashier Drawers">
                            <?php foreach ($drawers as $d): ?>
                                <?php $selected = $preFromKind === 'cashier_drawer' && $preFromId === (int) $d['id']; ?>
                                <option value="<?= (int) $d['id'] ?>" data-kind="cashier_drawer" data-balance="<?= e($d['current_balance']) ?>"
                                    <?= $selected ? 'selected' : '' ?>>
                                    💵 <?= e($d['name']) ?> (₹<?= number_format((float) $d['current_balance'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <input type="hidden" name="from_kind" id="from_kind" value="<?= e($preFromKind) ?>">
                    <div class="form-text small" id="from_balance_text"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">To <span class="text-danger">*</span></label>
                    <select name="to_id" class="form-select" id="to_id" required>
                        <option value="" data-kind="">— Select destination —</option>
                        <optgroup label="Bank Accounts">
                            <?php foreach ($banks as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" data-kind="bank_account">
                                    🏦 <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Cashier Drawers">
                            <?php foreach ($drawers as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" data-kind="cashier_drawer">
                                    💵 <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <input type="hidden" name="to_kind" id="to_kind" value="">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="transfer_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="transfer_date" name="transfer_date" required
                           class="form-control" value="<?= e(old('transfer_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="amount">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" required
                           class="form-control" value="<?= e(old('amount')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="mode">Mode</label>
                    <select id="mode" name="mode" class="form-select">
                        <?php foreach (['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'other'] as $m): ?>
                            <option value="<?= $m ?>" <?= old('mode') === $m ? 'selected' : '' ?>>
                                <?= strtoupper($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reference">Reference</label>
                    <input type="text" id="reference" name="reference" maxlength="100"
                           class="form-control" value="<?= e(old('reference')) ?>"
                           placeholder="UTR / cheque #">
                </div>
                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="form-control"><?= e(old('remarks')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Post Transfer</button>
        <a href="<?= url('/inter-account-transfers') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const fromSel = document.getElementById('from_id');
        const fromKind = document.getElementById('from_kind');
        const toSel = document.getElementById('to_id');
        const toKind = document.getElementById('to_kind');
        const fromBalText = document.getElementById('from_balance_text');

        function updateFrom() {
            const opt = fromSel.options[fromSel.selectedIndex];
            fromKind.value = opt?.dataset.kind || '';
            const bal = opt?.dataset.balance;
            fromBalText.textContent = bal !== undefined && bal !== ''
                ? 'Current balance: ₹' + parseFloat(bal).toFixed(2)
                : '';
        }
        function updateTo() {
            const opt = toSel.options[toSel.selectedIndex];
            toKind.value = opt?.dataset.kind || '';
        }
        fromSel.addEventListener('change', updateFrom);
        toSel.addEventListener('change', updateTo);
        updateFrom();
        updateTo();
    })();
</script>
