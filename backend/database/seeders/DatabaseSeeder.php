<?php

namespace Database\Seeders;

use App\Models\DictData;
use App\Models\DictType;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 创建超级管理员
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'username' => 'admin',
                'nickname' => '超级管理员',
                'name' => 'Admin',
                'password' => bcrypt('123456'),
                'status' => 1,
            ]
        );

        // 创建超级管理员角色
        $superRole = Role::firstOrCreate(
            ['name' => 'super-admin'],
            [
                'display_name' => '超级管理员',
                'description' => '拥有所有权限',
                'status' => 1,
                'sort' => 0,
            ]
        );

        // 分配角色
        $admin->roles()->syncWithoutDetaching([$superRole->id]);

        // 创建基础权限
        $permissions = [
            ['name' => 'system', 'display_name' => '系统管理', 'parent_id' => 0, 'sort' => 0],
            ['name' => 'system.user', 'display_name' => '用户管理', 'parent_id' => 0, 'sort' => 1],
            ['name' => 'system.user.create', 'display_name' => '创建用户', 'parent_id' => 0, 'sort' => 2],
            ['name' => 'system.user.edit', 'display_name' => '编辑用户', 'parent_id' => 0, 'sort' => 3],
            ['name' => 'system.user.delete', 'display_name' => '删除用户', 'parent_id' => 0, 'sort' => 4],
            ['name' => 'system.role', 'display_name' => '角色管理', 'parent_id' => 0, 'sort' => 5],
            ['name' => 'system.role.create', 'display_name' => '创建角色', 'parent_id' => 0, 'sort' => 6],
            ['name' => 'system.role.edit', 'display_name' => '编辑角色', 'parent_id' => 0, 'sort' => 7],
            ['name' => 'system.role.delete', 'display_name' => '删除角色', 'parent_id' => 0, 'sort' => 8],
            ['name' => 'system.permission', 'display_name' => '权限管理', 'parent_id' => 0, 'sort' => 9],
            ['name' => 'system.menu', 'display_name' => '菜单管理', 'parent_id' => 0, 'sort' => 10],
            ['name' => 'system.log', 'display_name' => '操作日志', 'parent_id' => 0, 'sort' => 11],
        ];

        $parentMap = [];
        foreach ($permissions as $perm) {
            $p = Permission::firstOrCreate(
                ['name' => $perm['name']],
                $perm
            );
            $parentMap[$perm['name']] = $p->id;
        }

        // 设置父级关系
        $relations = [
            'system.user' => 'system',
            'system.user.create' => 'system.user',
            'system.user.edit' => 'system.user',
            'system.user.delete' => 'system.user',
            'system.role' => 'system',
            'system.role.create' => 'system.role',
            'system.role.edit' => 'system.role',
            'system.role.delete' => 'system.role',
            'system.permission' => 'system',
            'system.menu' => 'system',
            'system.log' => 'system',
        ];

        foreach ($relations as $child => $parent) {
            if (isset($parentMap[$child]) && isset($parentMap[$parent])) {
                Permission::where('id', $parentMap[$child])->update(['parent_id' => $parentMap[$parent]]);
            }
        }

        // 创建基础菜单
        $systemMenu = Menu::firstOrCreate(
            ['path' => '/system'],
            [
                'parent_id' => 0,
                'title' => '系统管理',
                'icon' => 'el-icon-setting',
                'path' => '/system',
                'component' => 'Layout',
                'type' => 1,
                'sort' => 0,
                'status' => 1,
            ]
        );

        $menuItems = [
            ['title' => '用户管理', 'icon' => 'user', 'path' => 'user', 'component' => 'system/user', 'permission' => 'system.user', 'sort' => 1],
            ['title' => '角色管理', 'icon' => 'peoples', 'path' => 'role', 'component' => 'system/role', 'permission' => 'system.role', 'sort' => 2],
            ['title' => '权限管理', 'icon' => 'lock', 'path' => 'permission', 'component' => 'system/permission', 'permission' => 'system.permission', 'sort' => 3],
            ['title' => '菜单管理', 'icon' => 'tree-table', 'path' => 'menu', 'component' => 'system/menu', 'permission' => 'system.menu', 'sort' => 4],
            ['title' => '操作日志', 'icon' => 'documentation', 'path' => 'log', 'component' => 'system/log', 'permission' => 'system.log', 'sort' => 5],
            ['title' => '登录日志', 'icon' => 'eye-open', 'path' => 'login-log', 'component' => 'system/login-log', 'permission' => 'system.log', 'sort' => 6],
            ['title' => '文件管理', 'icon' => 'documentation', 'path' => 'file', 'component' => 'system/file', 'permission' => 'system', 'sort' => 7],
            ['title' => '系统配置', 'icon' => 'el-icon-s-tools', 'path' => 'config', 'component' => 'system/config', 'permission' => 'system', 'sort' => 8],
            ['title' => '数据字典', 'icon' => 'list', 'path' => 'dict', 'component' => 'system/dict', 'permission' => 'system', 'sort' => 9],
        ];

        foreach ($menuItems as $item) {
            Menu::firstOrCreate(
                ['path' => $item['path'], 'parent_id' => $systemMenu->id],
                array_merge($item, [
                    'parent_id' => $systemMenu->id,
                    'type' => 2,
                    'status' => 1,
                ])
            );
        }

        // 默认系统配置
        $defaultConfigs = [
            ['group' => 'site', 'key' => 'site_name', 'name' => '网站名称', 'value' => 'Admin', 'type' => 'text', 'sort' => 1],
            ['group' => 'site', 'key' => 'site_logo', 'name' => '网站Logo', 'value' => '', 'type' => 'image', 'sort' => 2],
            ['group' => 'site', 'key' => 'site_description', 'name' => '网站描述', 'value' => '后台管理系统', 'type' => 'textarea', 'sort' => 3],
            ['group' => 'site', 'key' => 'site_copyright', 'name' => '版权信息', 'value' => '© 2026 All Rights Reserved', 'type' => 'text', 'sort' => 4],
            ['group' => 'upload', 'key' => 'upload_max_size', 'name' => '最大上传大小(MB)', 'value' => '10', 'type' => 'number', 'sort' => 1],
            ['group' => 'upload', 'key' => 'upload_allowed_ext', 'name' => '允许上传格式', 'value' => 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx', 'type' => 'text', 'sort' => 2],
            ['group' => 'finance', 'key' => 'cny_exchange_rate', 'name' => '人民币汇率', 'value' => '7.2000', 'type' => 'number', 'description' => '用于将利润折算为人民币', 'sort' => 1],
        ];

        foreach ($defaultConfigs as $config) {
            SystemConfig::firstOrCreate(['key' => $config['key']], $config);
        }

        // 默认数据字典
        $dictGender = DictType::firstOrCreate(['code' => 'gender'], ['name' => '性别', 'code' => 'gender', 'status' => 1]);
        DictData::firstOrCreate(['dict_type_id' => $dictGender->id, 'value' => '1'], ['dict_type_id' => $dictGender->id, 'label' => '男', 'value' => '1', 'sort' => 1]);
        DictData::firstOrCreate(['dict_type_id' => $dictGender->id, 'value' => '2'], ['dict_type_id' => $dictGender->id, 'label' => '女', 'value' => '2', 'sort' => 2]);
        DictData::firstOrCreate(['dict_type_id' => $dictGender->id, 'value' => '0'], ['dict_type_id' => $dictGender->id, 'label' => '未知', 'value' => '0', 'sort' => 3]);

        $dictStatus = DictType::firstOrCreate(['code' => 'status'], ['name' => '通用状态', 'code' => 'status', 'status' => 1]);
        DictData::firstOrCreate(['dict_type_id' => $dictStatus->id, 'value' => '1'], ['dict_type_id' => $dictStatus->id, 'label' => '启用', 'value' => '1', 'sort' => 1]);
        DictData::firstOrCreate(['dict_type_id' => $dictStatus->id, 'value' => '0'], ['dict_type_id' => $dictStatus->id, 'label' => '禁用', 'value' => '0', 'sort' => 2]);

        $this->call(OrderStatusDictSeeder::class);
    }
}
