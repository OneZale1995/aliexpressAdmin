<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\SystemConfig;
use App\Services\LogisticsConfigResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BootstrapController extends Controller
{
    use ApiResponse;

    private const MENU_TREE_CACHE_KEY = 'bootstrap:menu_tree:v1';
    private const MENU_TREE_CACHE_TTL = 300;

    public function context(Request $request, LogisticsConfigResolver $resolver)
    {
        $user = $request->user();
        $user->load('roles');

        $menuTree = $this->getCachedMenuTree();

        $switches = [
            LogisticsConfigResolver::KEY_ENABLE_TEAM => $resolver->isSwitchOn(SystemConfig::getByKey(LogisticsConfigResolver::KEY_ENABLE_TEAM, '0')),
            LogisticsConfigResolver::KEY_ENABLE_USER => $resolver->isSwitchOn(SystemConfig::getByKey(LogisticsConfigResolver::KEY_ENABLE_USER, '0')),
        ];

        return $this->success([
            'user' => [
                'roles' => $user->getRoleNames() ?: ['default'],
                'permissions' => $user->getAllPermissions(),
                'introduction' => $user->nickname ?: $user->username,
                'avatar' => $user->avatar ?: 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
                'name' => $user->nickname ?: $user->username,
            ],
            'menus' => $menuTree,
            'logistics_switches' => $switches,
        ]);
    }

    private function getCachedMenuTree(): array
    {
        return Cache::remember(self::MENU_TREE_CACHE_KEY, self::MENU_TREE_CACHE_TTL, function () {
            $menus = Menu::query()->orderBy('sort')->get();
            return $this->buildTree($menus);
        });
    }

    private function buildTree($menus, int $parentId = 0): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ((int) $menu->parent_id !== $parentId) {
                continue;
            }

            $children = $this->buildTree($menus, (int) $menu->id);
            $item = $menu->toArray();
            if (!empty($children)) {
                $item['children'] = $children;
            }
            $tree[] = $item;
        }

        return $tree;
    }
}
