<?php
/**
 * Shared form body for employees/create and employees/edit.
 * Expects: $states, $idProofs, $users, $employee (?array — null on create)
 */
$employee = $employee ?? null;
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Personal</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="150"
                               class="form-control"
                               value="<?= e(old('name', $employee['name'] ?? '')) ?>" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" maxlength="20"
                               class="form-control"
                               value="<?= e(old('phone', $employee['phone'] ?? '')) ?>" required>
                        <div class="form-text">Must be unique.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="150"
                               class="form-control"
                               value="<?= e(old('email', $employee['email'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="state">State</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">— Not set —</option>
                            <?php $selectedState = old('state', $employee['state'] ?? ''); ?>
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
                                  class="form-control"><?= e(old('address', $employee['address'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Job</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="designation">Designation <span class="text-danger">*</span></label>
                        <input type="text" id="designation" name="designation" maxlength="150"
                               class="form-control"
                               value="<?= e(old('designation', $employee['designation'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="joining_date">Joining Date <span class="text-danger">*</span></label>
                        <input type="date" id="joining_date" name="joining_date"
                               class="form-control"
                               value="<?= e(old('joining_date', $employee['joining_date'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="salary_amount">Monthly Salary (₹)</label>
                        <input type="number" id="salary_amount" name="salary_amount" min="0" step="0.01"
                               class="form-control"
                               value="<?= e(old('salary_amount', (string) ($employee['salary_amount'] ?? '0'))) ?>">
                    </div>
                    <?php if (!empty($employee['exit_date'])): ?>
                        <div class="col-md-3">
                            <label class="form-label">Exit Date</label>
                            <input type="text" class="form-control" value="<?= e($employee['exit_date']) ?>" readonly>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>ID &amp; Tax (optional)</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="id_proof_type">ID Proof Type</label>
                        <select id="id_proof_type" name="id_proof_type" class="form-select">
                            <option value="">— None —</option>
                            <?php $selectedIdT = old('id_proof_type', $employee['id_proof_type'] ?? ''); ?>
                            <?php foreach ($idProofs as $t): ?>
                                <option value="<?= e($t) ?>" <?= $selectedIdT === $t ? 'selected' : '' ?>>
                                    <?= e($t) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="id_proof_number">ID Proof Number</label>
                        <input type="text" id="id_proof_number" name="id_proof_number" maxlength="50"
                               class="form-control"
                               value="<?= e(old('id_proof_number', $employee['id_proof_number'] ?? '')) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="pan">PAN</label>
                        <input type="text" id="pan" name="pan" maxlength="10"
                               class="form-control text-uppercase"
                               value="<?= e(old('pan', $employee['pan'] ?? '')) ?>"
                               pattern="[A-Z]{5}[0-9]{4}[A-Z]" placeholder="AAAPL1234C">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="aadhaar">Aadhaar</label>
                        <input type="text" id="aadhaar" name="aadhaar" maxlength="12"
                               class="form-control"
                               value="<?= e(old('aadhaar', $employee['aadhaar'] ?? '')) ?>"
                               pattern="\d{12}" placeholder="12 digits">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Salary Bank Account (optional)</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="bank_account_holder_name">Account Holder Name</label>
                        <input type="text" id="bank_account_holder_name" name="bank_account_holder_name" maxlength="150"
                               class="form-control"
                               value="<?= e(old('bank_account_holder_name', $employee['bank_account_holder_name'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="bank_account_number">Account Number</label>
                        <input type="text" id="bank_account_number" name="bank_account_number" maxlength="30"
                               class="form-control"
                               value="<?= e(old('bank_account_number', $employee['bank_account_number'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="bank_ifsc">IFSC</label>
                        <input type="text" id="bank_ifsc" name="bank_ifsc" maxlength="11"
                               class="form-control text-uppercase"
                               value="<?= e(old('bank_ifsc', $employee['bank_ifsc'] ?? '')) ?>"
                               pattern="[A-Z]{4}0[A-Z0-9]{6}" placeholder="HDFC0001234">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="bank_branch">Branch</label>
                        <input type="text" id="bank_branch" name="bank_branch" maxlength="150"
                               class="form-control"
                               value="<?= e(old('bank_branch', $employee['bank_branch'] ?? '')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>System Login (optional)</strong></div>
            <div class="card-body">
                <select name="linked_user_id" class="form-select">
                    <option value="">— None (no system login) —</option>
                    <?php $selectedUser = (int) old('linked_user_id', (string) ($employee['linked_user_id'] ?? 0)); ?>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $selectedUser ? 'selected' : '' ?>>
                            <?= e($u['name']) ?> (@<?= e($u['username']) ?> · <?= e($u['role_label'] ?? $u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text mt-2">
                    Link to a system user account if this employee logs in (e.g. cashier, manager).
                </div>
            </div>
        </div>

        <?php if (!empty($employee['linked_customer_id'])): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Customer Link</strong></div>
                <div class="card-body small">
                    Linked to customer record <strong>#<?= (int) $employee['linked_customer_id'] ?></strong>.
                    Lets this employee buy on credit, settled against salary.
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Remarks</strong></div>
            <div class="card-body">
                <textarea name="remarks" rows="4" maxlength="500"
                          class="form-control"><?= e(old('remarks', $employee['remarks'] ?? '')) ?></textarea>
            </div>
        </div>
    </div>
</div>
