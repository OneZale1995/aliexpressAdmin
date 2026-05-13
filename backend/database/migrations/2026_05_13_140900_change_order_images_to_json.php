<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 将现有单图转为 JSON 数组格式
        DB::statement("UPDATE orders SET purchase_image = JSON_ARRAY(purchase_image) WHERE purchase_image IS NOT NULL AND purchase_image != '' AND purchase_image NOT LIKE '[%'");
        DB::statement("UPDATE orders SET shipping_image = JSON_ARRAY(shipping_image) WHERE shipping_image IS NOT NULL AND shipping_image != '' AND shipping_image NOT LIKE '[%'");

        Schema::table('orders', function (Blueprint $table) {
            $table->json('purchase_image')->nullable()->change();
            $table->json('shipping_image')->nullable()->change();
        });
    }

    public function down(): void
    {
        // 取 JSON 数组第一个元素还原为字符串
        DB::statement("UPDATE orders SET purchase_image = JSON_UNQUOTE(JSON_EXTRACT(purchase_image, '$[0]')) WHERE purchase_image IS NOT NULL AND purchase_image LIKE '[%'");
        DB::statement("UPDATE orders SET shipping_image = JSON_UNQUOTE(JSON_EXTRACT(shipping_image, '$[0]')) WHERE shipping_image IS NOT NULL AND shipping_image LIKE '[%'");

        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchase_image', 500)->nullable()->change();
            $table->string('shipping_image', 500)->nullable()->change();
        });
    }
};
