<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Role::with('permissions');

        if ($request->filled('display_name')) {
            $query->where('display_name', 'like', '%' . $request->display_name . '%');
        }

        // 如果传了 all=1 不分页，返回全部（用于下拉选择）
        if ($request->get('all')) {
            return $this->success(Role::where('status', 1)->orderBy('sort')->get());
        }

        return $this->paginate($query, $request);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:191',
            'status' => 'in:0,1',
            'sort' => 'integer',
            'permission_ids' => 'array',
        ]);

        $role = Role::create($request->only(['name', 'display_name', 'description', 'status', 'sort']));

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        return $this->success($role->load('permissions'));
    }

    public function show(Request $request)
    {
        $role = Role::findOrFail($request->id);
        return $this->success($role->load('permissions'));
    }

    public function update(Request $request)
    {
        $role = Role::findOrFail($request->id);

        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name,' . $role->id,
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:191',
            'status' => 'in:0,1',
            'sort' => 'integer',
            'permission_ids' => 'array',
        ]);

        $role->update($request->only(['name', 'display_name', 'description', 'status', 'sort']));

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        return $this->success($role->load('permissions'));
    }

    public function destroy(Request $request)
    {
        $role = Role::findOrFail($request->id);
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return $this->success();
    }
}
