<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Categories (nested)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('type', ['drone', 'part', 'accessory', 'combo', 'other'])->default('drone');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->enum('visibility_mode', ['public', 'auth', 'restricted'])->default('public');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Brands
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->enum('type', ['simple', 'variable', 'bundle'])->default('simple');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('active');
            $table->boolean('is_featured')->default(false);
            $table->integer('warranty_months')->default(12);
            $table->boolean('requires_serial')->default(true);
            $table->boolean('requires_signature')->default(true);
            $table->text('casa_note')->nullable(); // CASA compliance advisory
            $table->integer('weight_g')->default(0);
            $table->integer('length_mm')->default(0);
            $table->integer('width_mm')->default(0);
            $table->integer('height_mm')->default(0);
            $table->enum('visibility_mode', ['public', 'auth', 'restricted'])->default('public');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->fullText(['name', 'short_description']);
        });

        // 4. Product Variants
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->json('options')->nullable(); // color, kit, combo options
            $table->string('barcode')->nullable();
            $table->integer('weight_g')->default(0);
            $table->unsignedBigInteger('cost_cents')->default(0);
            $table->integer('stock_qty')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('allow_backorder')->default(false);
            $table->string('status')->default('active');
            $table->integer('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        // 5. Product Prices (Role & Tier based)
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained('customer_groups')->cascadeOnDelete();
            $table->integer('min_qty')->default(1);
            $table->unsignedBigInteger('price_cents');
            $table->unsignedBigInteger('sale_price_cents')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['variant_id', 'customer_group_id', 'min_qty'], 'variant_group_qty_unique');
        });

        // 6. Visibility Rules (Polymorphic per customer group)
        Schema::create('visibility_rules', function (Blueprint $table) {
            $table->id();
            $table->string('visible_type');
            $table->unsignedBigInteger('visible_id');
            $table->foreignId('customer_group_id')->constrained('customer_groups')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['visible_type', 'visible_id', 'customer_group_id'], 'vis_rule_unique');
            $table->index(['visible_type', 'visible_id']);
        });

        // 7. Product Compatibility (Battery/Propeller -> Drone)
        Schema::create('product_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('drone_product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['part_product_id', 'drone_product_id'], 'compat_unique');
        });

        // 8. Bundle / Combo Items (Child stock decrements on sale)
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->timestamps();
        });

        // 9. Warranty Registrations (Unique serial)
        Schema::create('warranty_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->date('purchase_date');
            $table->string('purchased_from')->default('DroneHab Australia');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('proof_path')->nullable();
            $table->string('status')->default('active'); // active, expired, void
            $table->timestamps();

            $table->index('serial_number');
        });

        // 10. Warranty Claims (RMA)
        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // e.g. DH-RMA-100234
            $table->foreignId('registration_id')->constrained('warranty_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('issue_type');
            $table->text('description');
            $table->string('status')->default('submitted');
            $table->string('resolution_type')->nullable(); // repair, replace, refund
            $table->text('resolution_note')->nullable();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // 11. Service Jobs (Mackay Workshop / Field Agents)
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // e.g. DH-JOB-88412
            $table->foreignId('claim_id')->nullable()->constrained('warranty_claims')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('repair'); // repair, maintenance, inspection, thermal_calibration
            $table->string('status')->default('booked');
            $table->string('device_serial');
            $table->unsignedBigInteger('quote_cents')->default(0);
            $table->unsignedBigInteger('final_cents')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranty_registrations');
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('product_compatibility');
        Schema::dropIfExists('visibility_rules');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
