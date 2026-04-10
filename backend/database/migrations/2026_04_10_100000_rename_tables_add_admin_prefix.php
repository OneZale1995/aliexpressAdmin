<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = [
        'users'           => 'admin_users',
        'roles'           => 'admin_roles',
        'permissions'     => 'admin_permissions',
        'menus'           => 'admin_menus',
        'files'           => 'admin_files',
        'dict_types'      => 'admin_dict_types',
        'dict_data'       => 'admin_dict_data',
        'operation_logs'  => 'admin_operation_logs',
        'login_logs'      => 'admin_login_logs',
        'system_configs'  => 'admin_system_configs',
        'role_user'       => 'admin_role_user',
        'permission_role' => 'admin_permission_role',
    ];

    public function up(): void
    {
        foreach ($this->tables as $old => $new) {
            Schema::rename($old, $new);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $old => $new) {
            Schema::rename($new, $old);
        }
    }
};
