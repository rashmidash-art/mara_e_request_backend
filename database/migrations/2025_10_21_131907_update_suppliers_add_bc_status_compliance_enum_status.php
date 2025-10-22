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
        Schema::table('suppliers', function (Blueprint $table) {
            // Add new columns
            $table->string('bc_status')->nullable()->after('insurance_certificate'); // adjust column position if needed
            $table->string('compliance')->nullable()->after('bc_status');

            // Change 'status' from integer to enum
            $table->enum('status', ['Active', 'Suspended', 'Bad Rating', 'Inactive'])
                ->default('Active')
                ->comment('Active, Suspended, Bad Rating, Inactive')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Rollback changes
            $table->dropColumn(['bc_status', 'compliance']);
            $table->integer('status')->default(0)
                ->comment('0=Active, 1=Suspended, 2=Bad Rating, 3=Inactive')
                ->change();
        });
    }
};
