<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\IntegrityCheckService;

class IntegrityCheckController
{
    public function __construct(
        private IntegrityCheckService $service = new IntegrityCheckService(),
    ) {}

    public function show(): string
    {
        $checks = $this->service->runAll();
        $totalMismatches = 0;
        foreach ($checks as $c) { $totalMismatches += \count($c['mismatches']); }
        return view('system/integrity-check', [
            'title'           => 'System Integrity Check',
            'checks'          => $checks,
            'totalMismatches' => $totalMismatches,
            'ranAt'           => \date('Y-m-d H:i:s'),
        ]);
    }
}
