<?php
/**
 * Shared form body for users/create and users/edit.
 * Expects: $roles (array), $user (?array — null on create), $isSelf (?bool)
 */
$user   = $user   ?? null;
$isSelf = $isSelf ?? false;
$isEdit = $user !== null;
?>
<?php if ($isSelf): ?>
    <div class="alert alert-warning py-2 small">
        You are editing your own account. You cannot demote your own admin role or deactivate your own account.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Identity</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="150"
                               class="form-control"
                               value="<?= e(old('name', $user['name'] ?? '')) ?>" required autofocus>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                        <input type="text" id="username" name="username" maxlength="150"
                               class="form-control"
                               value="<?= e(old('username', $user['username'] ?? '')) ?>" required>
                        <div class="form-text">Must be unique. Many shops use phone number as username.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" maxlength="20"
                               class="form-control"
                               value="<?= e(old('phone', $user['phone'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="150"
                               class="form-control"
                               value="<?= e(old('email', $user['email'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" rows="2"
                                  class="form-control"><?= e(old('address', $user['address'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Password</strong></div>
            <div class="card-body">
                <?php if ($isEdit): ?>
                    <p class="small text-muted">Leave blank to keep the current password unchanged.</p>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="password">
                            Password <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <input type="password" id="password" name="password"
                               class="form-control"
                               autocomplete="new-password" minlength="6"
                               <?= $isEdit ? '' : 'required' ?>>
                        <div class="form-text">Minimum 6 characters.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Confirm Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control"
                               autocomplete="new-password" minlength="6"
                               <?= $isEdit ? '' : 'required' ?>>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Role &amp; Status</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                    <select id="role" name="role" class="form-select" required
                            <?= $isSelf && ($user['role'] ?? '') === 'admin' ? 'disabled' : '' ?>>
                        <?php $selectedRole = old('role', $user['role'] ?? 'subadmin'); ?>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= e($r['role_key']) ?>"
                                <?= $selectedRole === $r['role_key'] ? 'selected' : '' ?>>
                                <?= e($r['label']) ?>
                                <?= $r['role_key'] !== $r['label'] ? '(' . e($r['role_key']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isSelf && ($user['role'] ?? '') === 'admin'): ?>
                        <input type="hidden" name="role" value="admin">
                        <div class="form-text">Self-demotion blocked.</div>
                    <?php endif; ?>
                    <div class="form-text">
                        <a href="<?= url('/roles') ?>" class="text-decoration-none">
                            <i class="bi bi-shield-lock"></i> Manage roles
                        </a>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                    <select id="status" name="status" class="form-select" required
                            <?= $isSelf ? 'disabled' : '' ?>>
                        <?php $selectedStatus = old('status', $user['status'] ?? 'active'); ?>
                        <option value="active"   <?= $selectedStatus === 'active'   ? 'selected' : '' ?>>active</option>
                        <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>inactive</option>
                    </select>
                    <?php if ($isSelf): ?>
                        <input type="hidden" name="status" value="active">
                        <div class="form-text">Self-deactivation blocked.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
