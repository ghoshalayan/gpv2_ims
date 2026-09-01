<?php /** @var array $units */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-rulers"></i> Units</h1>
    <a href="<?= url('/units/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Unit
    </a>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle"></i>
    Within each <strong>family</strong>, one unit is the base and others convert to it
    (e.g. base <code>LTR</code>, then <code>ML</code> with factor <code>0.001</code> meaning
    1&nbsp;ML&nbsp;=&nbsp;0.001&nbsp;LTR). Each product picks one unit as its <em>stock unit</em>;
    additional buy/sell units are configured per-product on the product edit page.
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if ($units === []): ?>
            <p class="text-center text-muted py-4">No units defined yet.</p>
        <?php else: ?>
            <?php $byFamily = []; foreach ($units as $u) { $byFamily[$u['family']][] = $u; } ?>
            <?php foreach ($byFamily as $family => $rows): ?>
                <div class="border-top">
                    <div class="px-3 py-2 bg-body-tertiary small text-uppercase fw-semibold text-muted">
                        <?= e($family) ?> <span class="text-muted">(<?= count($rows) ?>)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Base</th>
                                    <th class="text-end">Factor → base</th>
                                    <th class="text-center">Decimals</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $u): $isBase = (int) $u['id'] === (int) $u['base_unit_id']; ?>
                                    <tr>
                                        <td class="font-monospace"><strong><?= e($u['code']) ?></strong>
                                            <?php if ($isBase): ?><span class="badge text-bg-secondary ms-1">base</span><?php endif; ?>
                                        </td>
                                        <td><?= e($u['name']) ?></td>
                                        <td class="font-monospace small"><?= e($u['base_code'] ?? '—') ?></td>
                                        <td class="text-end font-monospace">
                                            <?php if ($isBase): ?>
                                                <span class="text-muted">1.00</span>
                                            <?php else: ?>
                                                1 <?= e($u['code']) ?> = <?= rtrim(rtrim(number_format((float) $u['factor_to_base'], 8), '0'), '.') ?> <?= e($u['base_code']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= (int) $u['decimals'] ?></td>
                                        <td>
                                            <?php if ((int) $u['is_active'] === 1): ?>
                                                <span class="badge text-bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= e($u['description'] ?? '') ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="<?= url('/units/' . (int) $u['id'] . '/edit') ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="post" action="<?= url('/units/' . (int) $u['id'] . '/delete') ?>"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Delete <?= e($u['code']) ?>? Will fail if in use.')">
                                                <?= \App\Support\Csrf::field() ?>
                                                <button class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
