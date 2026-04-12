<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'backend_status')) {
                $table->string('backend_status', 50)->default('')->comment('后台状态')->after('admin_remark');
                $table->index('backend_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'backend_status')) {
                $table->dropIndex(['backend_status']);
                $table->dropColumn('backend_status');
            }
        });
    }
};
