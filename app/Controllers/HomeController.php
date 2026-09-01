<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Db;

class HomeController
{
    public function index(): string
    {
        $dbStatus = $this->probeDb();

        return view('home/index', [
            'title' => 'GPv2 IMS',
            'phpVersion' => PHP_VERSION,
            'appName' => env('APP_NAME', 'GPv2 IMS'),
            'appEnv' => env('APP_ENV', 'local'),
            'dbStatus' => $dbStatus,
        ]);
    }

    /** @return array{ok:bool, message:string, tables:int} */
    private function probeDb(): array
    {
        try {
            $pdo = Db::pdo();
            $count = (int) $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
            )->fetchColumn();
            return ['ok' => true, 'message' => 'Connected', 'tables' => $count];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'tables' => 0];
        }
    }
}
