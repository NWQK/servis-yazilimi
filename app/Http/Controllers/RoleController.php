<?php

namespace App\Http\Controllers;

use App\Services\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('manage role'), 403);
        $roleData = Role::where('parent_id', parentId())
            ->when(auth()->user()->type !== 'super admin', fn ($query) => $query->whereRaw('LOWER(TRIM(name)) != ?', ['client']))
            ->with('permissions')->get();
        return view('role.index', compact('roleData'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('create role'), 403);
        $permissionList = RolePermissions::available(auth()->user());
        return view('role.create', compact('permissionList'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create role'), 403);
        $available = RolePermissions::available(auth()->user())->pluck('id')->all();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('parent_id', parentId()), $this->reservedRoleName()],
            'user_permission' => ['required', 'array', 'min:1'],
            'user_permission.*' => ['integer', Rule::in($available)],
        ]);
        $role = Role::create(['name' => $data['title'], 'parent_id' => parentId(), 'guard_name' => 'web']);
        $role->syncPermissions(array_map('intval', $data['user_permission']));
        return redirect()->route('role.index')->with('success', __('Role successfully created.'));
    }

    public function edit($id)
    {
        abort_unless(auth()->user()->can('edit role'), 403);
        $role = Role::where('parent_id', parentId())->findOrFail($id);
        abort_if(auth()->user()->type !== 'super admin' && RolePermissions::isClient($role->name), 403);
        $permissionList = RolePermissions::available(auth()->user());
        $assignPermission = $role->permissions->pluck('id')->all();
        return view('role.edit', compact('role', 'permissionList', 'assignPermission'));
    }

    public function update(Request $request, $id)
    {
        abort_unless(auth()->user()->can('edit role'), 403);
        $role = Role::where('parent_id', parentId())->findOrFail($id);
        abort_if(auth()->user()->type !== 'super admin' && RolePermissions::isClient($role->name), 403);
        $available = RolePermissions::available(auth()->user())->pluck('id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)->where('parent_id', parentId()), $this->reservedRoleName($role)],
            'user_permission' => ['sometimes', 'array'],
            'user_permission.*' => ['integer', Rule::in($available)],
        ]);
        if (!in_array($role->name, ['employee', 'client'], true)) {
            $role->name = $data['name'];
            $role->save();
        }
        // Preserve active permissions the operator cannot edit; never trust hidden form IDs.
        $preserved = $role->permissions->filter(fn ($permission) =>
            !in_array($permission->id, $available) && RolePermissions::active($permission->name, auth()->user())
        )->pluck('id')->all();
        $role->syncPermissions(array_values(array_unique(array_merge($preserved, array_map('intval', $data['user_permission'] ?? [])))));
        return redirect()->route('role.index')->with('success', __('Role successfully updated.'));
    }

    public function destroy($id)
    {
        abort_unless(auth()->user()->can('delete role'), 403);
        $role = Role::where('parent_id', parentId())->findOrFail($id);
        abort_if(auth()->user()->type !== 'super admin' && RolePermissions::isClient($role->name), 403);
        abort_if(in_array($role->name, ['employee', 'client', 'owner', 'super admin'], true), 403);
        $role->delete();
        return redirect()->route('role.index')->with('success', __('Role successfully deleted.'));
    }
    private function reservedRoleName(?Role $role = null): \Closure
    {
        return function ($attribute, $value, $fail) use ($role) {
            if (RolePermissions::reserved((string)$value) && (!$role || strtolower(trim($role->name)) !== strtolower(trim((string)$value)))) {
                $fail('Bu rol adı sistem tarafından yönetilir; özel rol için farklı bir ad seçin.');
            }
            if (strtolower(trim((string)$value)) === 'branch_manager') {
                $fail('Şube merkez yöneticisi yalnızca süper yönetici tarafından oluşturulur.');
            }
            if (is_string($value) && RolePermissions::isClient($value) && (!$role || !RolePermissions::isClient($role->name))) {
                $fail('Müşteri rolü sistem tarafından yönetilir; bu adla rol oluşturulamaz.');
            }
        };
    }

}
