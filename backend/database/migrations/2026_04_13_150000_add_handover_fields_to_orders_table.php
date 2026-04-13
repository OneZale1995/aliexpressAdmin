<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('handover_list_id')->nullable()->after('logistic_order_id')->comment('FBS交接清单ID');
            $table->string('handover_list_status', 40)->nullable()->after('handover_list_id')->comment('FBS交接清单状态');
            $table->index('handover_list_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['handover_list_id']);
            $table->dropColumn(['handover_list_id', 'handover_list_status']);
        });
    }
};