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
              $table->string('categorei_id')->change()->comment('Stores multiple category IDs as CSV');
            $table->string('department_id')->change()->comment('Stores multiple department IDs as CSV');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
             $table->integer('categorei_id')->change()->comment('category Table');
            $table->integer('department_id')->change()->comment('department table');
        });
    }
};
