<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuController extends Controller
{
    use ApiResponse;

    private const MENU_TREE_CACHE_KEY = 'bootstrap:menu_tree:v1';
    private const MENU_TREE_CACHE_TTL = 300;

    public function index(Request $request)
    {
        return $this->success($this->getCachedMenuTree());
    }

    public function store(Request $request)
    {
        $request->validate([
            'parent_id' => 'integer',
            'title' => 'required|string|max:50',
            'icon' => 'nullable|string|max:50',
            'path' => 'nullable|string|max:191',
            'component' => 'nullable|string|max:191',
            'permission' => 'nullable|string|max:100',
            'type' => 'in:1,2,3',
            'hidden' => 'in:0,1',
            'sort' => 'integer',
            'status' => 'in:0,1',
        ]);

        $menu = Menu::create($request->only([
            'parent_id', 'title', 'icon', 'path', 'component',
            'permission', 'type', 'hidden', 'sort', 'status',
        ]));

        $this->clearMenuTreeCache();

        return $this->success($menu);
    }

    public function show(Request $request)
    {
        $menu = Menu::findOrFail($request->id);
        return $this->success($menu);
    }

    public function update(Request $request)
    {
        $menu = Menu::findOrFail($request->id);
        $request->validate([
            'parent_id' => 'integer',
            'title' => 'required|string|max:50',
            'icon' => 'nullable|string|max:50',
            'path' => 'nullable|string|max:191',
            'component' => 'nullable|string|max:191',
            'permission' => 'nullable|string|max:100',
            'type' => 'in:1,2,3',
            'hidden' => 'in:0,1',
            'sort' => 'integer',
            'status' => 'in:0,1',
        ]);

        $menu->update($request->only([
            'parent_id', 'title', 'icon', 'path', 'component',
            'permission', 'type', 'hidden', 'sort', 'status',
        ]));

        $this->clearMenuTreeCache();

        return $this->success($menu);
    }

    public function destroy(Request $request)
    {
        $menu = Menu::findOrFail($request->id);

        if (Menu::where('parent_id', $menu->id)->exists()) {
            return $this->error('请先删除子菜单');
        }

        $menu->delete();

        $this->clearMenuTreeCache();

        return $this->success();
    }

    private function clearMenuTreeCache(): void
    {
        Cache::forget(self::MENU_TREE_CACHE_KEY);
    }

    private function getCachedMenuTree(): array
    {
        return Cache::remember(self::MENU_TREE_CACHE_KEY, self::MENU_TREE_CACHE_TTL, function () {
            $menus = Menu::query()->orderBy('sort')->get();
            return $this->buildTree($menus);
        });
    }

    private function buildTree($menus, $parentId = 0)
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu->parent_id == $parentId) {
                $children = $this->buildTree($menus, $menu->id);
                $item = $menu->toArray();
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
}
