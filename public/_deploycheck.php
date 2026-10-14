<?php
/**
 * TEMPORARY deployment check. DELETE THIS FILE once the deploy is confirmed.
 *
 * Reports which application files on this server are current and which are
 * stale left-overs from an earlier upload. It reads source files only: no
 * database connection, no credentials, no business data.
 *
 * Visit:  https://your-site/_deploycheck.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * Each check: a file, and a string that exists ONLY in the current version.
 * Absence of the marker means that file was not overwritten by the upload.
 */
$checks = [
    'app/Middleware/AuthMiddleware.php' => [
        'marker' => 'Superadmin::check() && Tenant::check()',
        'why'    => 'Lets a superadmin work inside a business. Without it, Enter bounces back to the console.',
    ],
    'app/Support/Db.php' => [
        'marker' => 'function multiTenant',
        'why'    => 'Detects the converted database. The old name was controlAvailable() and caused the 500.',
    ],
    'app/Support/Tenant.php' => [
        'marker' => '_tenant_stamp',
        'why'    => 'Keeps the cross-tenant session guard in step when a business is selected.',
    ],
    'app/Controllers/AuthController.php' => [
        'marker' => 'attemptAcrossTenants',
        'why'    => 'Credential-based sign-in: the username decides the business, with no picker.',
    ],
    'app/Controllers/SuperadminController.php' => [
        'marker' => 'suggestedOwnerUsername',
        'why'    => 'Creates a business with a globally unique administrator username.',
    ],
    'app/Controllers/DashboardController.php' => [
        'marker' => 'currentIdentity',
        'why'    => 'Stops the dashboard dereferencing a null user for a superadmin.',
    ],
    'app/Repositories/UserRepository.php' => [
        'marker' => 'findLoginAcrossTenants',
        'why'    => 'Resolves which business a username belongs to at sign-in.',
    ],
    'app/Services/AuthService.php' => [
        'marker' => 'attemptAcrossTenants',
        'why'    => 'Verifies the password before any business is bound to the connection.',
    ],
    'app/Views/auth/login.php' => [
        'marker' => 'No business picker',
        'why'    => 'The sign-in screen must not list your businesses.',
    ],
    'app/Support/helpers.php' => [
        'marker' => 'function redirect',
        'why'    => 'The file whose absence took the site down.',
    ],
];

$rows = [];
$stale = 0;
$absent = 0;
foreach ($checks as $rel => $c) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $rows[] = [$rel, 'MISSING', $c['why']];
        $absent++;
        continue;
    }
    $src = (string) file_get_contents($path);
    if (strpos($src, $c['marker']) === false) {
        $rows[] = [$rel, 'STALE', $c['why']];
        $stale++;
    } else {
        $rows[] = [$rel, 'CURRENT', $c['why']];
    }
}

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Deployment check</title>
<style>
  body { font: 15px/1.5 system-ui, sans-serif; margin: 2rem auto; max-width: 60rem; padding: 0 1rem; color: #111; }
  table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
  th, td { text-align: left; padding: .5rem .6rem; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { background: #f4f4f5; }
  code { font-size: .9em; }
  .CURRENT { color: #0a7d32; font-weight: 600; }
  .STALE   { color: #b31412; font-weight: 600; }
  .MISSING { color: #b31412; font-weight: 600; }
  .verdict { padding: .9rem 1rem; border-radius: 6px; margin-top: 1.2rem; }
  .ok  { background: #e7f6ec; border: 1px solid #0a7d32; }
  .bad { background: #fdeceb; border: 1px solid #b31412; }
  .note { color: #555; font-size: .9em; }
</style></head><body>
<h1>Deployment check</h1>
<p class="note">PHP <?= htmlspecialchars(PHP_VERSION) ?> &middot;
   application root <code><?= htmlspecialchars($root) ?></code></p>

<table>
  <thead><tr><th>File</th><th>State</th><th>What it does</th></tr></thead>
  <tbody>
  <?php foreach ($rows as [$rel, $state, $why]): ?>
    <tr>
      <td><code><?= htmlspecialchars($rel) ?></code></td>
      <td class="<?= $state ?>"><?= $state ?></td>
      <td class="note"><?= htmlspecialchars($why) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($stale === 0 && $absent === 0): ?>
  <div class="verdict ok">
    <strong>All files are current.</strong> The upload overwrote everything.
    If something still misbehaves, it is not a stale file &mdash; tell Claude this result.
  </div>
<?php else: ?>
  <div class="verdict bad">
    <strong><?= $stale + $absent ?> file(s) did not update.</strong>
    Your extraction is not overwriting existing files, so fixes to files that were
    already on the server never landed.
    <p style="margin:.6rem 0 0">
      Fix: in File Manager rename <code>app</code> to <code>app_old</code>, extract the
      bundle again so a fresh <code>app</code> is created, then re-load this page.
      Rename rather than delete, so you can put it back instantly.
    </p>
  </div>
<?php endif; ?>

<p class="note" style="margin-top:1.5rem">
  <strong>Delete this file when you are done.</strong> It is a diagnostic, not part of the application.
</p>
</body></html>
