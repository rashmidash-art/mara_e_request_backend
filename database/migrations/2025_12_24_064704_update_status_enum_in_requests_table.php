<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE requests
            MODIFY COLUMN status
            ENUM('submitted', 'draft', 'deleted', 'withdraw')
            DEFAULT 'draft'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE requests
            MODIFY COLUMN status
            ENUM('submitted', 'draft', 'deleted')
            DEFAULT 'draft'
        ");
    }
};
