<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_operation_logs', function (Blueprint $table) {
            $table->integer('business_code')->nullable()->after('status_code')->comment('业务响应码');
            $table->boolean('is_success')->default(true)->after('business_code')->comment('业务是否成功');
            $table->index('is_success');
            $table->index('duration');
        });
    }

    public function down(): void
    {
        Schema::table('admin_operation_logs', function (Blueprint $table) {
            $table->dropIndex(['is_success']);
            $table->dropIndex(['duration']);
            $table->dropColumn(['business_code', 'is_success']);
        });
    }
};
