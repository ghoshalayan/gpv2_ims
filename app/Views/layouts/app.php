<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? env('APP_NAME')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        body {
            background: #eef0f3;
            padding-top: 56px;
            font-family: 'Segoe UI', Roboto, 'Open Sans', sans-serif;
        }

        /* Top navbar */
        .navbar-brand { letter-spacing: 0.5px; }

        /* Sidebar — dark gradient like legacy */
        .sidebar-wrap {
            background: linear-gradient(180deg, #212529 0%, #1a1d20 100%);
            color: #adb5bd;
            width: 250px;
            min-height: calc(100vh - 56px);
        }
        @media (min-width: 768px) {
            .sidebar-wrap {
                position: fixed;
                top: 56px;
                left: 0;
                bottom: 0;
                overflow-y: auto;
            }
            .main-content { margin-left: 250px; }
        }

        .sidebar-wrap .nav-link {
            color: #adb5bd;
            padding: 0.55rem 1rem;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }
        .sidebar-wrap .nav-link:hover { color: #fff; background: rgba(255,255,255,0.04); }
        .sidebar-wrap .nav-link.active {
            color: #fff;
            background: rgba(13,110,253,0.2);
            border-left-color: #0d6efd;
        }
        .sidebar-wrap .sidebar-link .right-icon i {
            transition: transform .2s;
        }
        .sidebar-wrap .sidebar-link[aria-expanded="true"] .right-icon i {
            transform: rotate(180deg);
        }
        .sidebar-section {
            color: #6c757d;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 1rem 1rem 0.5rem;
        }
        .sidebar-wrap hr.divider { border-color: #495057; margin: 0.25rem 1rem; }
        .sidebar-wrap .ps-4 { padding-left: 2.5rem !important; }
        .sidebar-wrap .placeholder-item {
            color: #6c757d; font-size: 0.8rem; font-style: italic;
            padding: 0.4rem 1rem 0.4rem 2.5rem;
        }

        /* Main content card spacing */
        .main-content { padding: 1.25rem; }

        /* Login screen — no sidebar, just centered card */
        .auth-shell {
            min-height: calc(100vh - 56px - 60px);
            display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark bg-gradient fixed-top">
    <div class="container-fluid">
        <?php if (\App\Support\Auth::check()): ?>
            <button class="navbar-toggler d-md-none border-0" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
        <?php endif; ?>
        <a class="navbar-brand text-uppercase fw-bold" href="<?= url('/') ?>"><?= e(env('APP_NAME')) ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="navbar-text text-white-50 small d-none d-lg-inline">
                <?= e(env('APP_ENV')) ?> &middot; php <?= e(PHP_VERSION) ?>
            </span>
            <?php if (\App\Support\Auth::check()): ?>
                <?php $u = \App\Support\Auth::user(); ?>
                <a class="nav-link text-white p-0" href="<?= url('/help') ?>"
                   aria-label="How it works" title="How it works — quick tutorial">
                    <i class="bi bi-question-circle fs-5"></i>
                </a>
                <?php if (\App\Support\Permissions::can('dashboard_alerts', 'view')): ?>
                    <!-- Critical-alerts notification bell (low stock + credit-limit breaches) -->
                    <div class="dropdown" id="alertBellWrap">
                        <a class="nav-link text-white position-relative p-0" href="#" data-bs-toggle="dropdown"
                           aria-label="Alerts" title="Critical alerts">
                            <i class="bi bi-bell fs-5"></i>
                            <span id="alertBadge"
                                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none"
                                  style="font-size:.6rem">0</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="min-width:320px;max-width:380px">
                            <h6 class="dropdown-header d-flex justify-content-between">
                                <span>Critical Alerts</span>
                                <a href="<?= url('/dashboard') ?>" class="small">Dashboard</a>
                            </h6>
                            <div id="alertBellBody" class="px-2 pb-2">
                                <div class="text-muted small px-2 py-3 text-center">Loading…</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="dropdown">
                    <a class="nav-link text-white dropdown-toggle p-0" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text">
                            <strong><?= e($u['name']) ?></strong><br>
                            <small class="text-muted">@<?= e($u['username']) ?> &middot; <?= e($u['role']) ?></small>
                        </span></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (\App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN)): ?>
                            <li><a class="dropdown-item" href="<?= url('/settings') ?>">
                                <i class="bi bi-gear"></i> System Settings
                            </a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= url('/logout') ?>">
                            <i class="bi bi-power"></i> Log Out
                        </a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= url('/login') ?>" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if (\App\Support\Auth::check()): ?>
    <div class="offcanvas-md offcanvas-start sidebar-wrap" id="sidebar">
        <div class="offcanvas-header d-md-none">
            <h6 class="offcanvas-title text-white mb-0"><?= e(env('APP_NAME')) ?></h6>
            <button type="button" class="btn-close btn-close-white"
                    data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
        </div>
        <div class="offcanvas-body p-0">
            <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>
        </div>
    </div>

    <main class="main-content">
        <?php foreach (\App\Support\Flash::pull() as $flash): ?>
            <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
<?php else: ?>
    <main class="auth-shell container">
        <div class="w-100">
            <?php foreach (\App\Support\Flash::pull() as $flash): ?>
                <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
        </div>
    </main>
<?php endif; ?>

<footer class="<?= \App\Support\Auth::check() ? 'main-content' : 'container' ?> py-3 text-center text-muted small">
    &copy; <?= date('Y') ?> <?= e(env('APP_NAME')) ?>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Sidebar scroll persistence — keeps the sidebar at the same vertical position
    // across page navigations. Uses sessionStorage so it resets on a new browser session.
    (function () {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        var KEY = 'sidebarScrollY';
        var saved = sessionStorage.getItem(KEY);
        if (saved !== null) {
            // Defer one tick so layout (collapsed groups expanding) doesn't reset our scroll.
            requestAnimationFrame(function () { sidebar.scrollTop = parseInt(saved, 10) || 0; });
        }
        // Save before unload — also throttled scroll save in case we never get the unload event.
        var saveTimer = null;
        sidebar.addEventListener('scroll', function () {
            if (saveTimer) return;
            saveTimer = setTimeout(function () {
                sessionStorage.setItem(KEY, String(sidebar.scrollTop));
                saveTimer = null;
            }, 150);
        });
        window.addEventListener('beforeunload', function () {
            sessionStorage.setItem(KEY, String(sidebar.scrollTop));
        });
    })();

    $(function () {
        $('table.data-table').each(function () {
            $(this).DataTable({
                pageLength: 25, order: [],
                language: { search: '', searchPlaceholder: 'Search…' }
            });
        });

        // Auto-init Select2 on every Bootstrap form-select.
        // Add class "no-select2" on a <select> to opt out.
        $('select.form-select:not(.no-select2)').each(function () {
            const $sel = $(this);
            // Only apply if there's enough room for a dropdown
            $sel.select2({
                theme: 'bootstrap-5',
                width: $sel.css('width'),
                placeholder: $sel.find('option[value=""]').text() || 'Select…',
                allowClear: $sel.find('option[value=""]').length > 0
            });
        });
    });

    // ---- Critical-alerts notification bell (app-wide, permission-gated server-side) ----
    (function () {
        const wrap = document.getElementById('alertBellWrap');
        if (!wrap) return;
        const badge = document.getElementById('alertBadge');
        const body  = document.getElementById('alertBellBody');
        const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        const esc = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };

        fetch('<?= url('/dashboard/alerts') ?>', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(r => r.json())
            .then(function (d) {
                const total = d.total || 0;
                if (total > 0) {
                    badge.textContent = total > 99 ? '99+' : total;
                    badge.classList.remove('d-none');
                }
                let html = '';
                if ((d.low_stock_count || 0) > 0) {
                    html += '<div class="small fw-semibold text-warning mt-1"><i class="bi bi-box-seam"></i> Low stock ('
                        + d.low_stock_count + (d.out_of_stock ? ', ' + d.out_of_stock + ' out' : '') + ')</div>';
                    html += '<ul class="list-unstyled small mb-2">' + (d.low_stock || []).map(x =>
                        '<li class="d-flex justify-content-between"><span class="text-truncate" style="max-width:200px">' + esc(x.name)
                        + '</span><span class="text-muted">' + Number(x.quantity_on_hand) + ' ' + esc(x.unit) + '</span></li>').join('') + '</ul>';
                }
                if ((d.credit_breach_count || 0) > 0) {
                    html += '<div class="small fw-semibold text-danger mt-1"><i class="bi bi-credit-card"></i> Over credit limit ('
                        + d.credit_breach_count + ')</div>';
                    html += '<ul class="list-unstyled small mb-0">' + (d.credit_breach || []).map(x =>
                        '<li class="d-flex justify-content-between"><span class="text-truncate" style="max-width:180px">' + esc(x.name)
                        + '</span><span class="text-danger">' + inr(x.outstanding) + '/' + inr(x.credit_limit) + '</span></li>').join('') + '</ul>';
                }
                body.innerHTML = html || '<div class="text-success small px-2 py-3 text-center"><i class="bi bi-shield-check"></i> All clear</div>';
            })
            .catch(() => { body.innerHTML = '<div class="text-muted small px-2 py-2">Could not load alerts.</div>'; });
    })();
</script>
</body>
</html>
