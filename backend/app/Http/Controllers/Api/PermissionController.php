<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        // 权限列表返回树形结构
        $permissions = Permission::orderBy('sort')->get();

        if ($request->get('tree')) {
            return $this->success($this->buildTree($permissions));
        }

        return $this->success($permissions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:permissions',
            'display_name' => 'required|string|max:100',
            'parent_id' => 'integer',
            'description' => 'nullable|string|max:191',
            'sort' => 'integer',
        ]);

        $permission = Permission::create($request->only([
            'name', 'display_name', 'guard_name', 'parent_id', 'description', 'sort',
        ]));

        return $this->success($permission);
    }

    public function show(Request $request)
    {
        $permission = Permission::findOrFail($request->id);
        return $this->success($permission);
    }

    public function update(Request $request)
    {
        $permission = Permission::findOrFail($request->id);

        $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name,' . $permission->id,
            'display_name' => 'required|string|max:100',
            'parent_id' => 'integer',
            'description' => 'nullable|string|max:191',
            'sort' => 'integer',
        ]);

        $permission->update($request->only([
            'name', 'display_name', 'guard_name', 'parent_id', 'description', 'sort',
        ]));

        return $this->success($permission);
    }

    public function destroy(Request $request)
    {
        $permission = Permission::findOrFail($request->id);

        // 检查是否有子权限
        if (Permission::where('parent_id', $permission->id)->exists()) {
            return $this->error('请先删除子权限');
        }

        $permission->roles()->detach();
        $permission->delete();

        return $this->success();
    }

    private function buildTree($permissions, $parentId = 0)
    {
        $tree = [];
        foreach ($permissions as $permission) {
            if ($permission->parent_id == $parentId) {
                $children = $this->buildTree($permissions, $permission->id);
                $item = $permission->toArray();
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
}
