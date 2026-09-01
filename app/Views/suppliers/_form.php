<?php
/**
 * Shared form body for suppliers/create and suppliers/edit.
 * Expects: $states (array), $supplier (?array — null on create)
 */
$supplier = $supplier ?? null;
?>
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
                               value="<?= e(old('name', $supplier['name'] ?? '')) ?>" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" maxlength="20"
                               class="form-control"
                               value="<?= e(old('phone', $supplier['phone'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="150"
                               class="form-control"
                               value="<?= e(old('email', $supplier['email'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="state">State</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">— Not set —</option>
                            <?php $selectedState = old('state', $supplier['state'] ?? ''); ?>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= e($st) ?>" <?= $selectedState === $st ? 'selected' : '' ?>>
                                    <?= e($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Used to determine CGST+SGST vs IGST on invoices.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" rows="2"
                                  class="form-control"><?= e(old('address', $supplier['address'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Tax</strong></div>
            <div class="card-body">
                <label class="form-label" for="gstin">GSTIN</label>
                <input type="text" id="gstin" name="gstin" maxlength="15"
                       class="form-control text-uppercase"
                       value="<?= e(old('gstin', $supplier['gstin'] ?? '')) ?>"
                       pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]"
                       placeholder="e.g. 19AAACR5055K1Z5">
                <div class="form-text">15 characters. Leave blank if the supplier is unregistered.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Remarks</strong></div>
            <div class="card-body">
                <textarea name="remarks" rows="4" maxlength="500"
                          class="form-control"><?= e(old('remarks', $supplier['remarks'] ?? '')) ?></textarea>
                <div class="form-text">Internal notes. Up to 500 characters.</div>
            </div>
        </div>
    </div>
</div>
