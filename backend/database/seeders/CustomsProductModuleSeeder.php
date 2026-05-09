<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CustomsProductModuleSeeder extends Seeder
{
    public function run(): void
    {
        // ========== 权限 ==========
        $permData = [
            ['name' => 'order.customs-product', 'display_name' => '报关商品管理', 'parent_id' => 0, 'sort' => 200],
            ['name' => 'order.customs-product.create', 'display_name' => '新增报关商品', 'parent_id' => 0, 'sort' => 201],
            ['name' => 'order.customs-product.edit', 'display_name' => '编辑报关商品', 'parent_id' => 0, 'sort' => 202],
            ['name' => 'order.customs-product.delete', 'display_name' => '删除报关商品', 'parent_id' => 0, 'sort' => 203],
        ];

        $permMap = [];
        foreach ($permData as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm['name']], $perm);
            $permMap[$perm['name']] = $p->id;
        }

        // 查找订单模块父权限（如果存在）
        $orderPerm = Permission::where('name', 'order')->first();
        $parentId = $orderPerm ? $orderPerm->id : 0;

        // 设置父级关系
        Permission::where('id', $permMap['order.customs-product'])->update(['parent_id' => $parentId]);
        Permission::where('id', $permMap['order.customs-product.create'])->update(['parent_id' => $permMap['order.customs-product']]);
        Permission::where('id', $permMap['order.customs-product.edit'])->update(['parent_id' => $permMap['order.customs-product']]);
        Permission::where('id', $permMap['order.customs-product.delete'])->update(['parent_id' => $permMap['order.customs-product']]);

        // ========== 角色权限分配 ==========
        $allPermIds = array_values($permMap);

        $teamAdminRole = Role::where('name', 'team-admin')->first();
        if ($teamAdminRole) {
            $teamAdminRole->permissions()->syncWithoutDetaching($allPermIds);
        }

        $purchaserRole = Role::where('name', 'purchaser')->first();
        if ($purchaserRole) {
            $purchaserRole->permissions()->syncWithoutDetaching($allPermIds);
        }

        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->permissions()->syncWithoutDetaching($allPermIds);
        }

        // ========== 菜单 ==========
        $orderMenu = Menu::where('path', '/order')->first();

        if (!$orderMenu) {
            $orderMenu = Menu::firstOrCreate(
                ['path' => '/order'],
                [
                    'parent_id' => 0,
                    'title' => '订单管理',
                    'icon' => 'el-icon-s-order',
                    'path' => '/order',
                    'component' => 'Layout',
                    'type' => 1,
                    'sort' => 3,
                    'status' => 1,
                ]
            );
        }

        Menu::firstOrCreate(
            ['path' => 'customs-product', 'parent_id' => $orderMenu->id],
            [
                'parent_id' => $orderMenu->id,
                'title' => '报关商品',
                'icon' => 'el-icon-goods',
                'path' => 'customs-product',
                'component' => 'system/customs-product',
                'permission' => 'order.customs-product',
                'type' => 2,
                'sort' => 3,
                'status' => 1,
            ]
        );
    }
}
