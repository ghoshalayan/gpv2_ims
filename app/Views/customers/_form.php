<?php
/**
 * Shared form body for customers/create and customers/edit.
 * Expects: $states (array), $salesmen (array), $customer (?array — null on create)
 */
$customer = $customer ?? null;
$isSalesmanMirror = !empty($customer['linked_salesman_id']);
$isEmployeeMirror = !empty($customer['linked_employee_id']);
?>
<?php if ($isSalesmanMirror || $isEmployeeMirror): ?>
    <div class="alert alert-info py-2 small">
        This customer record is auto-linked to
        <?php if ($isSalesmanMirror): ?>salesman&nbsp;#<?= (int) $customer['linked_salesman_id'] ?><?php endif; ?>
        <?php if ($isSalesmanMirror && $isEmployeeMirror): ?> and <?php endif; ?>
        <?php if ($isEmployeeMirror): ?>employee&nbsp;#<?= (int) $customer['linked_employee_id'] ?><?php endif; ?>.
        Name / phone / email / address / state are mirrored from the linked record.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Contact</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="150"
                               class="form-control"
                               value="<?= e(old('name', $customer['name'] ?? '')) ?>" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" maxlength="20"
                               class="form-control"
                               value="<?= e(old('phone', $customer['phone'] ?? '')) ?>" required>
                        <div class="form-text">Must be unique.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="150"
                               class="form-control"
                               value="<?= e(old('email', $customer['email'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="state">State</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">— Not set —</option>
                            <?php $selectedState = old('state', $customer['state'] ?? ''); ?>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= e($st) ?>" <?= $selectedState === $st ? 'selected' : '' ?>>
                                    <?= e($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">For CGST+SGST vs IGST determination.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" rows="2"
                                  class="form-control"><?= e(old('address', $customer['address'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Business &amp; Tax</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="gstin">GSTIN</label>
                        <input type="text" id="gstin" name="gstin" maxlength="15"
                               class="form-control text-uppercase"
                               value="<?= e(old('gstin', $customer['gstin'] ?? '')) ?>"
                               pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]"
                               placeholder="e.g. 19AAACR5055K1Z5">
                        <div class="form-text">Present = B2B (Tax Invoice). Blank = B2C (Bill of Supply).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="credit_limit">Credit Limit (₹)</label>
                        <input type="number" id="credit_limit" name="credit_limit"
                               min="0" step="0.01" class="form-control"
                               value="<?= e(old('credit_limit', (string) ($customer['credit_limit'] ?? '0'))) ?>">
                        <div class="form-text">0 = no limit. Otherwise, sales are blocked when outstanding exceeds this.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Default Salesman</strong></div>
            <div class="card-body">
                <select id="default_salesman_id" name="default_salesman_id" class="form-select">
                    <option value="">— None —</option>
                    <?php $selectedSm = (int) old('default_salesman_id', (string) ($customer['default_salesman_id'] ?? 0)); ?>
                    <?php foreach ($salesmen as $sm): ?>
                        <option value="<?= (int) $sm['id'] ?>"
                            <?= (int) $sm['id'] === $selectedSm ? 'selected' : '' ?>>
                            <?= e($sm['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text mt-2">Auto-selected on new sales. Can be overridden per sale.</div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Remarks</strong></div>
            <div class="card-body">
                <textarea name="remarks" rows="4" maxlength="500"
                          class="form-control"><?= e(old('remarks', $customer['remarks'] ?? '')) ?></textarea>
            </div>
        </div>
    </div>
</div>
