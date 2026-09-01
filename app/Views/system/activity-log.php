<?php
/** @var array  $rows */
/** @var array  $users */
/** @var array  $entityKinds */
/** @var array  $filters */
/** @var array  $actions */
/** @var array  $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-text"></i> Activity Log</h1>
    <span class="text-muted small">
        <?= number_format((int) $pg['total']) ?> total entries
    </span>
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small" for="f_user">User</label>
                <select id="f_user" name="user_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= ($filters['user_id'] ?? null) === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_action">Action</label>
                <select id="f_action" name="action" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= $a ?>" <?= ($filters['action'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_kind">Entity</label>
                <select id="f_kind" name="entity_kind" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($entityKinds as $k): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['entity_kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_id">Entity ID</label>
                <input type="number" id="f_id" name="entity_id" class="form-control form-control-sm"
                       value="<?= e((string) ($filters['entity_id'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_from">From</label>
                <input type="date" id="f_from" name="date_from" class="form-control form-control-sm"
                       value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_to">To</label>
                <input type="date" id="f_to" name="date_to" class="form-control form-control-sm"
                       value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/activity-log') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search label / notes / user…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($rows === []): ?>
            <div class="text-center text-muted py-4">No log entries match these filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Label</th>
                            <th>Notes</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $actionColors = [
                                'create'   => 'success',
                                'update'   => 'info',
                                'delete'   => 'danger',
                                'void'     => 'warning',
                                'refund'   => 'warning',
                                'finalize' => 'primary',
                                'cancel'   => 'secondary',
                                'restore'  => 'secondary',
                                'login'    => 'success',
                                'logout'   => 'secondary',
                                'purge'    => 'danger',
                            ];
                        ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $ac = $actionColors[$r['action']] ?? 'secondary';
                                $hasJson = !empty($r['before_json']) || !empty($r['after_json']);
                            ?>
                            <tr>
                                <td class="text-nowrap small font-monospace"><?= e($r['created_at']) ?></td>
                                <td class="small"><?= e($r['user_name'] ?? '—') ?>
                                    <?php if (!empty($r['user_id'])): ?>
                                        <span class="text-muted">#<?= (int) $r['user_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge text-bg-<?= $ac ?>"><?= e($r['action']) ?></span></td>
                                <td class="small font-monospace"><?= e($r['entity_kind']) ?>
                                    <?php if (!empty($r['entity_id'])): ?>
                                        <span class="text-muted">#<?= (int) $r['entity_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= e($r['entity_label'] ?? '') ?></td>
                                <td class="small">
                                    <?= e($r['notes'] ?? '') ?>
                                    <?php if ($hasJson): ?>
                                        <details class="d-inline-block ms-1">
                                            <summary class="text-muted small" style="cursor:pointer">diff</summary>
                                            <?php if (!empty($r['before_json'])): ?>
                                                <div class="small text-muted mt-1">before</div>
                                                <pre class="bg-light p-2 mb-1 small"><?= e($r['before_json']) ?></pre>
                                            <?php endif; ?>
                                            <?php if (!empty($r['after_json'])): ?>
                                                <div class="small text-muted">after</div>
                                                <pre class="bg-light p-2 mb-0 small"><?= e($r['after_json']) ?></pre>
                                            <?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted font-monospace"><?= e($r['ip_address'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
