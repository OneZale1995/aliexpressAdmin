<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class FrontendMenuAlignmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedMenus();
    }

    private function seedPermissions(): void
    {
        $permissionDefs = [
            ['name' => 'order', 'display_name' => '订单管理', 'parent' => null, 'sort' => 300],
            ['name' => 'order.manage', 'display_name' => '订单列表', 'parent' => 'order', 'sort' => 301],
            ['name' => 'order.statistics', 'display_name' => '订单统计', 'parent' => 'order', 'sort' => 302],
            ['name' => 'order.blacklist', 'display_name' => '订单黑名单', 'parent' => 'order', 'sort' => 303],

            ['name' => 'team', 'display_name' => '团队管理', 'parent' => null, 'sort' => 320],
            ['name' => 'team.manage', 'display_name' => '我的团队', 'parent' => 'team', 'sort' => 321],
            ['name' => 'team.user', 'display_name' => '采购用户管理', 'parent' => 'team', 'sort' => 322],

            ['name' => 'shop', 'display_name' => '店铺管理', 'parent' => null, 'sort' => 340],
            ['name' => 'shop.store', 'display_name' => '店铺管理', 'parent' => 'shop', 'sort' => 341],
            ['name' => 'shop.logistics-config', 'display_name' => '物流配置', 'parent' => 'shop', 'sort' => 342],

            ['name' => 'product', 'display_name' => '商品管理', 'parent' => null, 'sort' => 360],
            ['name' => 'product.list', 'display_name' => '商品列表', 'parent' => 'product', 'sort' => 361],
            ['name' => 'product.export', 'display_name' => '商品导出', 'parent' => 'product', 'sort' => 362],
        ];

        $permissionMap = [];

        foreach ($permissionDefs as $def) {
            $permission = Permission::firstOrCreate(
                ['name' => $def['name']],
                [
                    'name' => $def['name'],
                    'display_name' => $def['display_name'],
                    'parent_id' => 0,
                    'sort' => $def['sort'],
                ]
            );

            $permissionMap[$def['name']] = $permission->id;
        }

        foreach ($permissionDefs as $def) {
            if (!$def['parent']) {
                continue;
            }

            if (!isset($permissionMap[$def['name']], $permissionMap[$def['parent']])) {
                continue;
            }

            Permission::where('id', $permissionMap[$def['name']])->update([
                'parent_id' => $permissionMap[$def['parent']],
            ]);
        }

        $rolePermissions = [
            'super-admin' => array_column($permissionDefs, 'name'),
            'team-admin' => [
                'order', 'order.manage', 'order.statistics', 'order.blacklist', 'order.customs-product',
                'team', 'team.manage', 'team.user',
                'shop', 'shop.user', 'shop.user.create', 'shop.user.edit', 'shop.user.delete',
                'shop.team', 'shop.team.create', 'shop.team.edit', 'shop.team.delete',
                'shop.store', 'shop.store.create', 'shop.store.edit', 'shop.store.delete',
                'shop.logistics-config',
            ],
            'purchaser' => [
                'order', 'order.manage', 'order.statistics', 'order.blacklist', 'order.customs-product',
                'shop', 'shop.store', 'shop.store.create', 'shop.store.edit', 'shop.logistics-config',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }

            $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id')->all();
            if (!empty($permissionIds)) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
        }
    }

    private function seedMenus(): void
    {
        $topMenus = [
            ['path' => '/order', 'title' => '订单管理', 'icon' => 'el-icon-s-order', 'sort' => 30],
            ['path' => '/team', 'title' => '团队管理', 'icon' => 'peoples', 'sort' => 40],
            ['path' => '/shop', 'title' => '店铺管理', 'icon' => 'el-icon-s-shop', 'sort' => 50],
            ['path' => '/product', 'title' => '商品管理', 'icon' => 'el-icon-goods', 'sort' => 60],
            ['path' => '/system', 'title' => '系统管理', 'icon' => 'el-icon-setting', 'sort' => 70],
        ];

        $menuMap = [];
        foreach ($topMenus as $menu) {
            $menuModel = Menu::updateOrCreate(
                ['path' => $menu['path']],
                [
                    'parent_id' => 0,
                    'title' => $menu['title'],
                    'icon' => $menu['icon'],
                    'path' => $menu['path'],
                    'component' => 'Layout',
                    'type' => 1,
                    'sort' => $menu['sort'],
                    'status' => 1,
                    'hidden' => 0,
                ]
            );

            $menuMap[$menu['path']] = $menuModel->id;
        }

        $children = [
            ['/order', 'index', '订单', 'el-icon-s-order', 'order/index', 'order.manage', 1],
            ['/order', 'statistics', '订单统计', 'el-icon-data-analysis', 'order/statistics', 'order.statistics', 2],
            ['/order', 'blacklist', '订单黑名单', 'el-icon-warning-outline', 'order/blacklist', 'order.blacklist', 3],
            ['/order', 'customs-product', '报关商品', 'el-icon-goods', 'system/customs-product', 'order.customs-product', 4],

            ['/team', 'index', '我的团队', 'peoples', 'team/index', 'shop.team', 1],
            ['/team', 'user', '采购用户管理', 'user', 'team/user', 'shop.user', 2],

            ['/shop', 'index', '店铺管理', 'el-icon-s-shop', 'shop/index', 'shop.store', 1],
            ['/shop', 'logistics-config', '物流配置', 'el-icon-setting', 'shop/logistics-config', 'shop.logistics-config', 2],

            ['/product', 'list', '商品列表', 'el-icon-goods', 'product/index', 'product.list', 1],
            ['/product', 'export', '商品导出', 'el-icon-download', 'product/export', 'product.export', 2],

            ['/system', 'user', '用户管理', 'user', 'system/user', 'system.user', 1],
            ['/system', 'role', '角色管理', 'peoples', 'system/role', 'system.role', 2],
            ['/system', 'permission', '权限管理', 'lock', 'system/permission', 'system.permission', 3],
            ['/system', 'menu', '菜单管理', 'tree-table', 'system/menu', 'system.menu', 4],
            ['/system', 'log', '操作日志', 'documentation', 'system/log', 'system.log', 5],
            ['/system', 'login-log', '登录日志', 'eye-open', 'system/login-log', 'system.log', 6],
            ['/system', 'file', '文件管理', 'documentation', 'system/file', 'system', 7],
            ['/system', 'config', '系统配置', 'el-icon-s-tools', 'system/config', 'system', 8],
            ['/system', 'dict', '数据字典', 'list', 'system/dict', 'system', 9],
        ];

        foreach ($children as [$parentPath, $path, $title, $icon, $component, $permission, $sort]) {
            if (!isset($menuMap[$parentPath])) {
                continue;
            }

            Menu::updateOrCreate(
                [
                    'parent_id' => $menuMap[$parentPath],
                    'path' => $path,
                ],
                [
                    'parent_id' => $menuMap[$parentPath],
                    'title' => $title,
                    'icon' => $icon,
                    'path' => $path,
                    'component' => $component,
                    'permission' => $permission,
                    'type' => 2,
                    'sort' => $sort,
                    'status' => 1,
                    'hidden' => 0,
                ]
            );
        }
    }
}
