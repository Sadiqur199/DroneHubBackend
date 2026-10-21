<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend products with the fields the storefront + admin uploader need.
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->after('brand_id');
            }
            if (!Schema::hasColumn('products', 'subcategory')) {
                $table->string('subcategory')->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('products', 'model_number')) {
                $table->string('model_number')->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'image_url')) {
                $table->string('image_url')->nullable()->after('short_description');
            }
            if (!Schema::hasColumn('products', 'flight_time')) {
                $table->string('flight_time')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('products', 'drone_id')) {
                $table->foreignId('drone_id')->nullable()->constrained('products')->nullOnDelete()->after('flight_time');
            }
            if (!Schema::hasColumn('products', 'specs')) {
                $table->json('specs')->nullable()->after('drone_id');
            }
        });

        // 2. Order line items (referenced by OrderController but never created).
        if (!Schema::hasTable('part_order_items')) {
            Schema::create('part_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('part_order_id')->constrained('part_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('part_name');
                $table->decimal('original_price', 10, 2)->default(0.00);
                $table->decimal('price', 10, 2)->default(0.00);
                $table->decimal('discount', 10, 2)->default(0.00);
                $table->string('discount_type')->default('flat');
                $table->unsignedInteger('quantity')->default(1);
                $table->timestamps();

                $table->index('part_order_id');
            });
        }

        // 3. Tutorials table (referenced by TutorialController but never created).
        if (!Schema::hasTable('tutorials')) {
            Schema::create('tutorials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('drone_id')->nullable();
                $table->string('category')->default('Getting Started');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('duration')->default('5:00');
                $table->string('video_url')->nullable();
                $table->string('thumbnail')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 4. API bearer tokens (stateless auth for the SPA).
        if (!Schema::hasTable('api_tokens')) {
            Schema::create('api_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->string('name')->default('spa');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['category_id', 'subcategory', 'model_number', 'image_url', 'flight_time', 'drone_id', 'specs'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('part_order_items');
        Schema::dropIfExists('tutorials');
        Schema::dropIfExists('api_tokens');
    }
};
