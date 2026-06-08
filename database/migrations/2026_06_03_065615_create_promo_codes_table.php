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
    Schema::create('promo_codes', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->enum('discount_type', ['percentage', 'fixed']);
        $table->decimal('discount_value', 10, 2);
        $table->decimal('minimum_order_amount', 10, 2)->nullable();
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('promo_codes');
}
};
