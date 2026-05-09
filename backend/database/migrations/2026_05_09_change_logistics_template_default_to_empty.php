<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('logistics_template', 30)->default('')->comment('物流模板: fbs/leiyi/chinapost')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('logistics_template', 30)->default('online')->comment('物流模板: online/offline_leiyi/offline_epacket')->change();
        });
    }
};
