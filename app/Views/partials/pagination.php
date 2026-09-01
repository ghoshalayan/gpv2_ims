<?php
/**
 * Reusable server-side pagination + search + per-page-selector partial.
 *
 * Required local var:
 *   $pg — array produced by Paginator::buildContext()
 *
 * Optional local var:
 *   $searchPlaceholder — text shown in the search input (default 'Search…')
 *
 * Renders a search box at the top, then a footer row with page nav + per-page +
 * result-count text. Drop into any list view replacing the `data-table` markup.
 *
 * @var array $pg
 */
$searchPlaceholder = $searchPlaceholder ?? 'Search…';
$baseUrl           = $pg['base_url'];
$extra             = $pg['extra_query'];
$pageLink          = $pg['page_link'];
$current           = (int) $pg['page'];
$totalPages        = (int) $pg['total_pages'];
?>

<form method="get" action="<?= e($baseUrl) ?>" class="d-flex gap-2 align-items-center mb-2">
    <?php foreach ($extra as $k => $v): ?>
        <?php if ($k === 'q' || $k === 'page') { continue; } ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
    <?php endforeach; ?>
    <input type="text" name="q" class="form-control form-control-sm" style="max-width:320px"
           placeholder="<?= e($searchPlaceholder) ?>" value="<?= e($pg['search']) ?>">
    <select name="per_page" class="form-select form-select-sm no-select2" style="width:90px"
            onchange="this.form.submit()">
        <?php foreach ($pg['per_page_options'] as $opt): ?>
            <option value="<?= (int) $opt ?>" <?= (int) $pg['per_page'] === (int) $opt ? 'selected' : '' ?>>
                <?= (int) $opt ?>/page
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
    <?php if ($pg['search'] !== ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e($pageLink(1, $pg['per_page'], '')) ?>">Clear</a>
    <?php endif; ?>
    <span class="ms-auto text-muted small">
        <?php if ($pg['total'] === 0): ?>
            No matches
        <?php else: ?>
            <?= number_format($pg['from']) ?>–<?= number_format($pg['to']) ?> of <?= number_format($pg['total']) ?>
        <?php endif; ?>
    </span>
</form>

<?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-between align-items-center mt-2">
        <span class="text-muted small">Page <?= $current ?> of <?= $totalPages ?></span>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($pg['first_link']) ?>" aria-label="First">«</a>
            </li>
            <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($pg['prev_link']) ?>" aria-label="Previous">‹</a>
            </li>
            <?php
                // Show up to 5 numeric page links centered around the current page.
                $rangeStart = max(1, $current - 2);
                $rangeEnd   = min($totalPages, $rangeStart + 4);
                $rangeStart = max(1, $rangeEnd - 4);
                for ($p = $rangeStart; $p <= $rangeEnd; $p++):
            ?>
                <li class="page-item <?= $p === $current ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e($pageLink($p)) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $current >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($pg['next_link']) ?>" aria-label="Next">›</a>
            </li>
            <li class="page-item <?= $current >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($pg['last_link']) ?>" aria-label="Last">»</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
