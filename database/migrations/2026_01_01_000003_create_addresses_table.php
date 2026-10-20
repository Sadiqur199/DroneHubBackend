<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['billing', 'shipping'])->default('shipping');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('suburb'); // Australian suburb
            $table->string('state', 10); // NSW, VIC, QLD, WA, SA, TAS, ACT, NT
            $table->string('postcode', 10);
            $table->string('country', 2)->default('AU');
            $table->string('phone');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'type', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
