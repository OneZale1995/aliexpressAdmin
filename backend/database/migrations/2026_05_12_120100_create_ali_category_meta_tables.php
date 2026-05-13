<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_id', 32)->unique();
            $table->string('parent_id', 32)->default('')->index();
            $table->string('name', 255)->default('');
            $table->boolean('is_leaf')->default(false);
            $table->boolean('is_special')->default(false);
            $table->boolean('is_visible')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ali_category_properties', function (Blueprint $table) {
            $table->id();
            $table->string('category_id', 32)->index();
            $table->string('property_id', 32)->index();
            $table->boolean('is_sku_property')->default(false)->index();
            $table->string('name', 255)->default('');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_key')->default(false);
            $table->boolean('is_brand')->default(false);
            $table->boolean('is_enum_prop')->default(false);
            $table->boolean('is_multi_select')->default(false);
            $table->boolean('is_input_prop')->default(false);
            $table->boolean('has_unit')->default(false);
            $table->boolean('has_customized_pic')->default(false);
            $table->boolean('has_customized_name')->default(false);
            $table->json('units')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'property_id', 'is_sku_property'], 'ali_cat_props_unique');
        });

        Schema::create('ali_category_property_values', function (Blueprint $table) {
            $table->id();
            $table->string('category_id', 32)->index();
            $table->string('property_id', 32)->index();
            $table->string('value_id', 32)->index();
            $table->boolean('is_sku_property')->default(false)->index();
            $table->string('shipping_template_id', 32)->default('')->index();
            $table->string('name', 255)->default('');
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique([
                'category_id',
                'property_id',
                'value_id',
                'is_sku_property',
                'shipping_template_id',
            ], 'ali_cat_prop_vals_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_category_property_values');
        Schema::dropIfExists('ali_category_properties');
        Schema::dropIfExists('ali_categories');
    }
};
