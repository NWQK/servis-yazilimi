<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function __construct()
    {
        // Global permission definitions affect every tenant, including the internal client role.
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()?->type === 'super admin', 403);
            return $next($request);
        });
    }


    public function index()
    {
        $permissionData = Permission::orderBy('id', 'desc')->get();
        return view('user_permission.index', compact('permissionData'));
    }


    public function create()
    {
        $userRoles = Role::get()->pluck('name','id')->map(fn ($name) => \App\Services\RolePermissions::label($name));
        return view('user_permission.create', compact('userRoles'));
    }


    public function store(Request $request)
    {

        $validator = \Validator::make(
            $request->all(), [
                'title' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }
        $permissions=explode(',',$request->title);
        foreach ($permissions as $permission){
            $userPermission = new Permission();
            $userPermission->name = $permission;
            $userPermission->save();
            if (!empty($request->user_roles)) {
                foreach ($request->user_roles as $userRole) {
                    $role = Role::find($userRole);
                    $permissionArr = Permission::where('name', $permission)->first();
                    $role->givePermissionTo($permissionArr);
                }
            }
        }
        return redirect()->back()->with('success', __('Permission successfully created.'));
    }


    public function destroy($id)
    {
        $permission = Permission::find($id);
        $permission->delete();
        return redirect()->back()->with('success', __('Permission successfully deleted.'));
    }
}
