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
    Schema::table('orders', function (Blueprint $table) {
        $table->string('full_name')->nullable()->after('user_id');
        $table->string('phone_number')->nullable()->after('full_name');

        $table->foreignId('promo_code_id')
            ->nullable()
            ->after('suite_apt')
            ->index();

        $table->decimal('subtotal', 10, 2)->default(0)->after('promo_code_id');
        $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropColumn([
            'full_name',
            'phone_number',
            'promo_code_id',
            'subtotal',
            'discount_amount',
        ]);
    });
}
};
