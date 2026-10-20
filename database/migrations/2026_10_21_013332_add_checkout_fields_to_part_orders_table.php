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
        Schema::table('part_orders', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('bank_transfer_proof');
            $table->string('shipping_method')->nullable()->after('address');
            $table->decimal('shipping_cost', 10, 2)->default(0.00)->after('shipping_method');
            $table->decimal('gst_amount', 10, 2)->default(0.00)->after('total_price');
            $table->text('notes')->nullable()->after('gst_amount');
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('drone_id')->nullable()->change();
        });
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
