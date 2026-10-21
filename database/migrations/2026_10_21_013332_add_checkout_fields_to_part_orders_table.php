<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('part_orders')) {
            Schema::create('part_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('drone_id')->nullable();
                $table->string('order_number')->unique();
                $table->string('name');
                $table->string('email');
                $table->string('phone');
                $table->text('address');
                $table->string('shipping_method')->nullable();
                $table->decimal('shipping_cost', 10, 2)->default(0.00);
                $table->string('po_number')->nullable();
                $table->string('company')->nullable();
                $table->string('bank_transfer_proof')->nullable();
                $table->string('document_path')->nullable();
                $table->text('notes')->nullable();
                $table->decimal('total_price', 12, 2)->default(0.00);
                $table->decimal('total_discount', 10, 2)->default(0.00);
                $table->decimal('gst_amount', 10, 2)->default(0.00);
                $table->string('status')->default('Pending');
                $table->timestamps();
            });
        } else {
            Schema::table('part_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('part_orders', 'document_path')) {
                    $table->string('document_path')->nullable();
                }
                if (!Schema::hasColumn('part_orders', 'shipping_method')) {
                    $table->string('shipping_method')->nullable();
                }
                if (!Schema::hasColumn('part_orders', 'shipping_cost')) {
                    $table->decimal('shipping_cost', 10, 2)->default(0.00);
                }
                if (!Schema::hasColumn('part_orders', 'gst_amount')) {
                    $table->decimal('gst_amount', 10, 2)->default(0.00);
                }
                if (!Schema::hasColumn('part_orders', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_orders', function (Blueprint $table) {
            //
        });
    }
};
