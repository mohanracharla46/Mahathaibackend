<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = collect([
                'uber_quote_id',
                'uber_quote_expires_at',
            ])->filter(fn (string $column) => Schema::hasColumn('orders', $column))->all();

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'uber_quote_id')) {
                $table->string('uber_quote_id')->nullable()->after('uber_delivery_id');
            }

            if (! Schema::hasColumn('orders', 'uber_quote_expires_at')) {
                $table->timestamp('uber_quote_expires_at')->nullable()->after('uber_quote_id');
            }
        });
    }
};
