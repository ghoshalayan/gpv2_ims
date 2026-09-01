<?php
/**
 * Shared form body for salesmen/create and salesmen/edit.
 * Expects: $states (array), $salesman (?array — null on create)
 */
$salesman = $salesman ?? null;
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
                               value="<?= e(old('name', $salesman['name'] ?? '')) ?>" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" maxlength="20"
                               class="form-control"
                               value="<?= e(old('phone', $salesman['phone'] ?? '')) ?>" required>
                        <div class="form-text">Must be unique.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="150"
                               class="form-control"
                               value="<?= e(old('email', $salesman['email'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="state">State</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">— Not set —</option>
                            <?php $selectedState = old('state', $salesman['state'] ?? ''); ?>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= e($st) ?>" <?= $selectedState === $st ? 'selected' : '' ?>>
                                    <?= e($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" rows="2"
                                  class="form-control"><?= e(old('address', $salesman['address'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Commission &amp; Tax</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="commission_rate">Default Commission (%)</label>
                        <input type="number" id="commission_rate" name="commission_rate"
                               min="0" max="100" step="0.01" class="form-control"
                               value="<?= e(old('commission_rate', (string) ($salesman['commission_rate'] ?? '0'))) ?>">
                        <div class="form-text">Applied to sales by default. Overridable per sale line.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="gstin">GSTIN</label>
                        <input type="text" id="gstin" name="gstin" maxlength="15"
                               class="form-control text-uppercase"
                               value="<?= e(old('gstin', $salesman['gstin'] ?? '')) ?>"
                               pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]"
                               placeholder="e.g. 19AAACR5055K1Z5">
                        <div class="form-text">15 characters. Leave blank if unregistered.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Remarks</strong></div>
            <div class="card-body">
                <textarea name="remarks" rows="4" maxlength="500"
                          class="form-control"><?= e(old('remarks', $salesman['remarks'] ?? '')) ?></textarea>
            </div>
        </div>

        <?php if (!empty($salesman['linked_customer_id'])): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Customer Link</strong></div>
                <div class="card-body small">
                    Linked to customer record <strong>#<?= (int) $salesman['linked_customer_id'] ?></strong>.
                    This lets the salesman also buy on credit, with the balance settled against earned commission.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
