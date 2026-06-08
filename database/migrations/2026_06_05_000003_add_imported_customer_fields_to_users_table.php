<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_ordered_on')->nullable()->after('created_at');
            $table->boolean('following_email')->default(false)->after('last_ordered_on');
            $table->boolean('following_sms')->default(false)->after('following_email');
            $table->integer('points_remaining')->default(0)->after('following_sms');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_ordered_on',
                'following_email',
                'following_sms',
                'points_remaining',
            ]);
        });
    }
};
