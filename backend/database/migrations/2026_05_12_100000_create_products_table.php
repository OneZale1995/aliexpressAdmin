<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->index();
            $table->string('ae_item_id', 64)->index();
            $table->string('category_id', 64)->default('');
            $table->string('title_en', 512)->default('');
            $table->string('title_ru', 512)->default('');
            $table->string('main_image_url', 1024)->default('');
            $table->string('status_type', 32)->default('');
            $table->json('properties')->nullable()->comment('商品主属性，来自 get-seller-product property 字段');
            $table->json('skus')->nullable()->comment('SKU及变体属性，来自 get-seller-product sku 字段');
            $table->json('raw_data')->nullable()->comment('API完整原始数据');
            $table->timestamp('ae_created_at')->nullable();
            $table->timestamp('ae_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable()->comment('最近一次同步时间');
            $table->timestamps();

            $table->unique(['shop_id', 'ae_item_id']);
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
