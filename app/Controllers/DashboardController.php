<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DashboardService;
use App\Support\Auth;
use App\Support\Permissions;

class DashboardController
{
    /**
     * Component registry: ordering + which permission gates each, and which
     * DashboardService method produces its KPIs. "Urgent" components render
     * first. Adding a component here + a resource in MenuPermissionRegistry is
     * all it takes to make it role-assignable.
     *
     * @var array<int, array{key:string, perm:string, method:string, urgent:bool}>
     */
    private const COMPONENTS = [
        ['key' => 'alerts',       'perm' => 'dashboard_alerts',      'method' => 'alerts',     'urgent' => true],
        ['key' => 'sales',        'perm' => 'dashboard_sales',       'method' => 'sales',      'urgent' => true],
        ['key' => 'cash_bank',    'perm' => 'dashboard_cash_bank',   'method' => 'cashBank',   'urgent' => true],
        ['key' => 'purchase',     'perm' => 'dashboard_purchase',    'method' => 'purchase',   'urgent' => false],
        ['key' => 'profit_loss',  'perm' => 'dashboard_profit_loss', 'method' => 'profitLoss', 'urgent' => false],
        ['key' => 'inventory',    'perm' => 'dashboard_inventory',   'method' => 'inventory',  'urgent' => false],
        ['key' => 'customers',    'perm' => 'dashboard_customers',   'method' => 'customers',  'urgent' => false],
        ['key' => 'salesmen',     'perm' => 'dashboard_salesmen',    'method' => 'salesmen',   'urgent' => false],
    ];

    public function __construct(private DashboardService $svc = new DashboardService()) {}

    public function index(): string
    {
        // Only the components this user is permitted to see, in declared order.
        $visible = \array_values(\array_filter(
            self::COMPONENTS,
            static fn (array $c) => Permissions::can($c['perm'], 'view')
        ));

        return view('dashboard/index', [
            'title'      => 'Dashboard',
            'user'       => Auth::user(),
            'components' => $visible,
        ]);
    }

    /** AJAX: KPI payload for every permitted component (one round-trip). */
    public function data(): string
    {
        $plPeriod = (string) ($_GET['pl_period'] ?? 'month');
        if (!\in_array($plPeriod, ['month', 'quarter', 'half', 'year'], true)) {
            $plPeriod = 'month';
        }

        $out = [];
        foreach (self::COMPONENTS as $c) {
            if (!Permissions::can($c['perm'], 'view')) {
                continue;
            }
            $out[$c['key']] = $c['method'] === 'profitLoss'
                ? $this->svc->profitLoss($plPeriod)
                : $this->svc->{$c['method']}();
        }

        return $this->json(['ok' => true, 'data' => $out]);
    }

    /** AJAX: lightweight alert feed for the navbar bell (permission-gated). */
    public function alerts(): string
    {
        if (!Permissions::can('dashboard_alerts', 'view')) {
            return $this->json(['ok' => true, 'total' => 0, 'low_stock' => [], 'credit_breach' => []]);
        }
        $a = $this->svc->alerts();
        return $this->json([
            'ok'                  => true,
            'total'               => $a['total'],
            'low_stock_count'     => $a['low_stock_count'],
            'out_of_stock'        => $a['out_of_stock'],
            'credit_breach_count' => $a['credit_breach_count'],
            'low_stock'           => \array_slice($a['low_stock'], 0, 8),
            'credit_breach'       => \array_slice($a['credit_breach'], 0, 8),
        ]);
    }

    private function json(array $payload): string
    {
        \header('Content-Type: application/json; charset=utf-8');
        return \json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
