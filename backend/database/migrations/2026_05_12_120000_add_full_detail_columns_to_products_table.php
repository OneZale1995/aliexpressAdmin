<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('owner_member_id', 128)->default('');
            $table->string('owner_member_seq', 128)->default('');
            $table->integer('bulk_discount')->nullable();
            $table->integer('bulk_order')->nullable();
            $table->string('base_unit', 64)->default('');
            $table->string('add_unit', 64)->default('');
            $table->decimal('add_weight', 10, 3)->nullable();
            $table->string('currency_code', 16)->default('');
            $table->string('delivery_time', 64)->default('');
            $table->string('freight_template_id', 64)->default('');
            $table->string('package_height', 64)->default('');
            $table->string('package_length', 64)->default('');
            $table->string('package_width', 64)->default('');
            $table->string('lot_num', 64)->default('');
            $table->string('unit', 32)->default('');
            $table->string('promise_template_id', 64)->default('');
            $table->string('product_reduce_strategy', 64)->default('');
            $table->decimal('gross_weight', 10, 3)->nullable();
            $table->string('sizechart_id', 64)->default('');
            $table->boolean('package_type')->nullable();
            $table->json('descriptions')->nullable();
            $table->json('media')->nullable();
            $table->json('subjects')->nullable();
            $table->json('marketing_images')->nullable();
            $table->json('keywords')->nullable();
            $table->string('gtin', 128)->default('');
            $table->json('classifier_codes')->nullable();
            $table->longText('detail')->nullable();
            $table->longText('mobile_detail')->nullable();
            $table->string('ticket_allocate_type', 64)->default('');
            $table->json('video')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'owner_member_id',
                'owner_member_seq',
                'bulk_discount',
                'bulk_order',
                'base_unit',
                'add_unit',
                'add_weight',
                'currency_code',
                'delivery_time',
                'freight_template_id',
                'package_height',
                'package_length',
                'package_width',
                'lot_num',
                'unit',
                'promise_template_id',
                'product_reduce_strategy',
                'gross_weight',
                'sizechart_id',
                'package_type',
                'descriptions',
                'media',
                'subjects',
                'marketing_images',
                'keywords',
                'gtin',
                'classifier_codes',
                'detail',
                'mobile_detail',
                'ticket_allocate_type',
                'video',
            ]);
        });
    }
};
