<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('sku_sync_status', 32)
                ->default('pending')
                ->after('sku_attributes')
                ->index()
                ->comment('SKU属性同步状态');
        });

        DB::table('order_items')
            ->whereNotNull('sku_attributes')
            ->update(['sku_sync_status' => 'synced']);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('sku_sync_status');
        });
    }
};