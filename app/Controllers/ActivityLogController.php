<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ActivityLogRepository;
use App\Repositories\UserRepository;
use App\Support\Paginator;

class ActivityLogController
{
    public function __construct(
        private ActivityLogRepository $repo  = new ActivityLogRepository(),
        private UserRepository        $users = new UserRepository(),
    ) {}

    public function index(): string
    {
        $filters = [
            'user_id'     => (int) ($_GET['user_id'] ?? 0) ?: null,
            'entity_kind' => (string) ($_GET['entity_kind'] ?? '') ?: null,
            'action'      => (string) ($_GET['action'] ?? '') ?: null,
            'date_from'   => (string) ($_GET['date_from'] ?? '') ?: null,
            'date_to'     => (string) ($_GET['date_to'] ?? '') ?: null,
            'entity_id'   => (int) ($_GET['entity_id'] ?? 0) ?: null,
        ];

        $p = Paginator::fromQuery();
        $result = $this->repo->paginate($filters + ['search' => $p->search], $p->page, $p->perPage);

        return view('system/activity-log', [
            'title'        => 'Activity Log',
            'rows'         => $result['rows'],
            'users'        => $this->users->all(),
            'entityKinds'  => $this->repo->distinctEntityKinds(),
            'filters'      => $filters,
            'actions'      => ['create', 'update', 'delete', 'void', 'refund', 'finalize', 'cancel', 'restore', 'login', 'logout', 'purge'],
            'pg'           => $p->buildContext(
                $result['total'],
                url('/activity-log'),
                $filters + ['q' => $p->search]
            ),
        ]);
    }
}
