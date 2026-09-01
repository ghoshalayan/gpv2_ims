<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UnitRepository;
use App\Support\ActivityLog;
use App\Support\Auth;
use App\Support\Flash;

class UnitController
{
    private const FAMILIES = ['count', 'mass', 'volume', 'length', 'pack', 'other'];

    public function __construct(private UnitRepository $repo = new UnitRepository()) {}

    public function index(): string
    {
        return view('units/index', [
            'title' => 'Units',
            'units' => $this->repo->all(),
        ]);
    }

    public function create(): string
    {
        return view('units/create', [
            'title'    => 'New Unit',
            'families' => self::FAMILIES,
            'bases'    => $this->repo->listActive(),
        ]);
    }

    public function store(): string
    {
        [$data, $err] = $this->validate($_POST, '/units/create');
        if ($err !== null) { return $err; }
        if ($this->repo->existsByCode($data['code'])) {
            flash_old($_POST);
            Flash::error("Unit code '{$data['code']}' is already taken.");
            return redirect('/units/create');
        }
        $id = $this->repo->insert($data, (int) Auth::id());
        ActivityLog::log('create', 'unit', $id, ['label' => $data['code']]);
        Flash::success("Unit '{$data['code']}' created.");
        return redirect('/units');
    }

    public function edit(array $params): string
    {
        $unit = $this->require((int) $params['id']);
        if (\is_string($unit)) { return $unit; }
        return view('units/edit', [
            'title'    => 'Edit Unit',
            'unit'     => $unit,
            'families' => self::FAMILIES,
            'bases'    => $this->repo->listActive(),
        ]);
    }

    public function update(array $params): string
    {
        $id   = (int) $params['id'];
        $unit = $this->require($id);
        if (\is_string($unit)) { return $unit; }

        [$data, $err] = $this->validate($_POST, "/units/{$id}/edit");
        if ($err !== null) { return $err; }
        if ($this->repo->existsByCode($data['code'], $id)) {
            flash_old($_POST);
            Flash::error("Unit code '{$data['code']}' is already taken.");
            return redirect("/units/{$id}/edit");
        }
        $this->repo->update($id, $data, (int) Auth::id());
        ActivityLog::log('update', 'unit', $id, ['label' => $data['code']]);
        Flash::success("Unit '{$data['code']}' updated.");
        return redirect('/units');
    }

    public function destroy(array $params): string
    {
        $id   = (int) $params['id'];
        $unit = $this->require($id);
        if (\is_string($unit)) { return $unit; }

        if ($this->repo->isInUse($id)) {
            Flash::error("Cannot delete '{$unit['code']}' — it's in use by products or referenced as a base unit.");
            return redirect('/units');
        }
        $this->repo->delete($id);
        ActivityLog::log('delete', 'unit', $id, ['label' => $unit['code']]);
        Flash::success("Unit '{$unit['code']}' deleted.");
        return redirect('/units');
    }

    /** @return array{0:array, 1:?string} */
    private function validate(array $input, string $redirectTo): array
    {
        $code   = \strtoupper(\trim((string) ($input['code']   ?? '')));
        $name   = \trim((string) ($input['name']   ?? ''));
        $family = (string) ($input['family'] ?? 'count');
        $baseId = (int) ($input['base_unit_id'] ?? 0) ?: null;
        $factor = (float) ($input['factor_to_base'] ?? 1);
        $dec    = (int) ($input['decimals'] ?? 3);
        $active = !empty($input['is_active']) ? 1 : 0;
        $desc   = \trim((string) ($input['description'] ?? '')) ?: null;

        $errs = [];
        if ($code === '' || !\preg_match('/^[A-Z][A-Z0-9_]*$/', $code)) {
            $errs[] = 'Code must be uppercase letters/digits/underscores (start with a letter).';
        }
        if ($name === '') { $errs[] = 'Name is required.'; }
        if (!\in_array($family, self::FAMILIES, true)) { $errs[] = 'Family is invalid.'; }
        if ($factor <= 0)   { $errs[] = 'Factor must be > 0.'; }
        if ($dec < 0 || $dec > 6) { $errs[] = 'Decimals must be 0–6.'; }

        if ($baseId !== null) {
            $base = $this->repo->find($baseId);
            if ($base === null) { $errs[] = 'Base unit not found.'; }
            elseif ((string) $base['family'] !== $family) {
                $errs[] = "Base unit family ({$base['family']}) doesn't match this unit's family ({$family}).";
            }
        }

        if ($errs !== []) {
            flash_old($input);
            Flash::error($errs[0]);
            return [[], redirect($redirectTo)];
        }
        return [[
            'code' => $code, 'name' => $name, 'family' => $family,
            'base_unit_id' => $baseId, 'factor_to_base' => $factor,
            'decimals' => $dec, 'is_active' => $active, 'description' => $desc,
        ], null];
    }

    private function require(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Unit not found.');
            return redirect('/units');
        }
        return $row;
    }
}
