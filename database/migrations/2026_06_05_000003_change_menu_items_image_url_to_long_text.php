<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE menu_items MODIFY image_url LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_items MODIFY image_url VARCHAR(2048) NULL');
    }
};
