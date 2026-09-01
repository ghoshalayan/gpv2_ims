<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\InterAccountTransferRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class BankAccountController
{
    public function __construct(
        private BankAccountRepository          $repo      = new BankAccountRepository(),
        private InterAccountTransferRepository $transfers = new InterAccountTransferRepository(),
    ) {}

    public function index(): string
    {
        return view('bank-accounts/index', [
            'title'    => 'Bank Accounts',
            'accounts' => $this->repo->all(),
        ]);
    }

    public function create(): string
    {
        return view('bank-accounts/create', ['title' => 'New Bank Account']);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/bank-accounts/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }
        if ($this->repo->existsByName($data['name'])) {
            flash_old($_POST);
            Flash::error("A bank account named '{$data['name']}' already exists.");
            return redirect('/bank-accounts/create');
        }
        $this->repo->insert($data, (int) Auth::id());
        Flash::success("Bank account '{$data['name']}' created.");
        return redirect('/bank-accounts');
    }

    public function show(array $params): string
    {
        $account = $this->require((int) $params['id']);
        if (\is_string($account)) { return $account; }
        return view('bank-accounts/show', [
            'title'     => $account['name'],
            'account'   => $account,
            'transfers' => $this->transfers->byAccount('bank_account', (int) $account['id']),
        ]);
    }

    public function edit(array $params): string
    {
        $account = $this->require((int) $params['id']);
        if (\is_string($account)) { return $account; }
        return view('bank-accounts/edit', [
            'title'   => 'Edit Bank Account',
            'account' => $account,
        ]);
    }

    public function update(array $params): string
    {
        $id      = (int) $params['id'];
        $account = $this->require($id);
        if (\is_string($account)) { return $account; }
        [$data, $errorRedirect] = $this->validateInput($_POST, "/bank-accounts/{$id}/edit");
        if ($errorRedirect !== null) { return $errorRedirect; }
        if ($this->repo->existsByName($data['name'], $id)) {
            flash_old($_POST);
            Flash::error("Another bank account named '{$data['name']}' already exists.");
            return redirect("/bank-accounts/{$id}/edit");
        }
        $this->repo->update($id, $data, (int) Auth::id());
        Flash::success("Bank account '{$data['name']}' updated.");
        return redirect('/bank-accounts');
    }

    public function toggleStatus(array $params): string
    {
        $id      = (int) $params['id'];
        $account = $this->require($id);
        if (\is_string($account)) { return $account; }
        $newStatus = $account['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Bank account '{$account['name']}' is now {$newStatus}.");
        return redirect('/bank-accounts');
    }

    /** @return array{0:array, 1:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $data = [
            'name'            => \trim((string) ($input['name'] ?? '')),
            'bank_name'       => \trim((string) ($input['bank_name'] ?? '')) ?: null,
            'account_number'  => \trim((string) ($input['account_number'] ?? '')) ?: null,
            'ifsc'            => \trim((string) ($input['ifsc'] ?? '')) ?: null,
            'branch'          => \trim((string) ($input['branch'] ?? '')) ?: null,
            'opening_balance' => (float) ($input['opening_balance'] ?? 0),
            'remarks'         => \trim((string) ($input['remarks'] ?? '')) ?: null,
        ];
        $v = Validator::make([
                'name' => $data['name'],
            ])
            ->required('name')
            ->max('name', 100);
        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    private function require(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Bank account not found.');
            return redirect('/bank-accounts');
        }
        return $row;
    }
}
