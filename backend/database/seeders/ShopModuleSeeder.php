<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ShopModuleSeeder extends Seeder
{
    public function run(): void
    {
        // ========== 角色 ==========
        $teamAdminRole = Role::firstOrCreate(
            ['name' => 'team-admin'],
            [
                'display_name' => '团队管理员',
                'description' => '管理团队用户、团队、店铺',
                'status' => 1,
                'sort' => 10,
            ]
        );

        $purchaserRole = Role::firstOrCreate(
            ['name' => 'purchaser'],
            [
                'display_name' => '采购',
                'description' => '管理自己的店铺',
                'status' => 1,
                'sort' => 20,
            ]
        );

        // ========== 权限 ==========
        $permData = [
            // 店铺模块
            ['name' => 'shop', 'display_name' => '店铺模块', 'parent_id' => 0, 'sort' => 100],

            // 团队用户管理
            ['name' => 'shop.user', 'display_name' => '团队用户管理', 'parent_id' => 0, 'sort' => 101],
            ['name' => 'shop.user.create', 'display_name' => '添加采购用户', 'parent_id' => 0, 'sort' => 102],
            ['name' => 'shop.user.edit', 'display_name' => '编辑采购用户', 'parent_id' => 0, 'sort' => 103],
            ['name' => 'shop.user.delete', 'display_name' => '删除采购用户', 'parent_id' => 0, 'sort' => 104],

            // 团队管理
            ['name' => 'shop.team', 'display_name' => '团队管理', 'parent_id' => 0, 'sort' => 110],
            ['name' => 'shop.team.create', 'display_name' => '创建团队', 'parent_id' => 0, 'sort' => 111],
            ['name' => 'shop.team.edit', 'display_name' => '编辑团队', 'parent_id' => 0, 'sort' => 112],
            ['name' => 'shop.team.delete', 'display_name' => '删除团队', 'parent_id' => 0, 'sort' => 113],

            // 店铺管理
            ['name' => 'shop.store', 'display_name' => '店铺管理', 'parent_id' => 0, 'sort' => 120],
            ['name' => 'shop.store.create', 'display_name' => '添加店铺', 'parent_id' => 0, 'sort' => 121],
            ['name' => 'shop.store.edit', 'display_name' => '编辑店铺', 'parent_id' => 0, 'sort' => 122],
            ['name' => 'shop.store.delete', 'display_name' => '删除店铺', 'parent_id' => 0, 'sort' => 123],
        ];

        $permMap = [];
        foreach ($permData as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm['name']], $perm);
            $permMap[$perm['name']] = $p->id;
        }

        // 设置父级关系
        $relations = [
            'shop.user' => 'shop',
            'shop.user.create' => 'shop.user',
            'shop.user.edit' => 'shop.user',
            'shop.user.delete' => 'shop.user',
            'shop.team' => 'shop',
            'shop.team.create' => 'shop.team',
            'shop.team.edit' => 'shop.team',
            'shop.team.delete' => 'shop.team',
            'shop.store' => 'shop',
            'shop.store.create' => 'shop.store',
            'shop.store.edit' => 'shop.store',
            'shop.store.delete' => 'shop.store',
        ];

        foreach ($relations as $child => $parent) {
            if (isset($permMap[$child], $permMap[$parent])) {
                Permission::where('id', $permMap[$child])->update(['parent_id' => $permMap[$parent]]);
            }
        }

        // ========== 团队管理员权限分配 ==========
        // 团队用户管理（只能添加采购用户）、团队管理（允许改名）、店铺管理
        $teamAdminPermissions = [
            'shop',
            'shop.user', 'shop.user.create', 'shop.user.edit', 'shop.user.delete',
            'shop.team', 'shop.team.edit',
            'shop.store', 'shop.store.create', 'shop.store.edit', 'shop.store.delete',
        ];

        $teamAdminPermIds = [];
        foreach ($teamAdminPermissions as $name) {
            if (isset($permMap[$name])) {
                $teamAdminPermIds[] = $permMap[$name];
            }
        }
        $teamAdminRole->permissions()->syncWithoutDetaching($teamAdminPermIds);

        // ========== 采购权限分配 ==========
        // 只有店铺管理自己的店
        $purchaserPermissions = [
            'shop',
            'shop.store', 'shop.store.create', 'shop.store.edit',
        ];

        $purchaserPermIds = [];
        foreach ($purchaserPermissions as $name) {
            if (isset($permMap[$name])) {
                $purchaserPermIds[] = $permMap[$name];
            }
        }
        $purchaserRole->permissions()->syncWithoutDetaching($purchaserPermIds);

        // ========== 菜单 ==========
        $shopMenu = Menu::firstOrCreate(
            ['path' => '/shop'],
            [
                'parent_id' => 0,
                'title' => '店铺管理',
                'icon' => 'el-icon-s-shop',
                'path' => '/shop',
                'component' => 'Layout',
                'type' => 1,
                'sort' => 5,
                'status' => 1,
            ]
        );

        $shopMenuItems = [
            ['title' => '团队管理', 'icon' => 'peoples', 'path' => 'team', 'component' => 'shop/team', 'permission' => 'shop.team', 'sort' => 1],
            ['title' => '店铺管理', 'icon' => 'el-icon-s-shop', 'path' => 'index', 'component' => 'shop/index', 'permission' => 'shop.store', 'sort' => 2],
        ];

        foreach ($shopMenuItems as $item) {
            Menu::firstOrCreate(
                ['path' => $item['path'], 'parent_id' => $shopMenu->id],
                array_merge($item, [
                    'parent_id' => $shopMenu->id,
                    'type' => 2,
                    'status' => 1,
                ])
            );
        }
    }
}
