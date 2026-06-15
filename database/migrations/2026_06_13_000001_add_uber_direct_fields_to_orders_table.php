<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('uber_delivery_id')->nullable()->after('status')->index();
            $table->string('uber_delivery_status')->nullable()->after('uber_delivery_id');
            $table->string('uber_tracking_url')->nullable()->after('uber_delivery_status');
            $table->unsignedInteger('uber_delivery_fee')->nullable()->after('uber_tracking_url');
            $table->json('uber_delivery_response')->nullable()->after('uber_delivery_fee');
            $table->text('uber_delivery_error')->nullable()->after('uber_delivery_response');
            $table->timestamp('uber_delivery_dispatched_at')->nullable()->after('uber_delivery_error');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'uber_delivery_id',
                'uber_delivery_status',
                'uber_tracking_url',
                'uber_delivery_fee',
                'uber_delivery_response',
                'uber_delivery_error',
                'uber_delivery_dispatched_at',
            ]);
        });
    }
};
