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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->change();
            $table->integer('entiti_id')->nullable()->change();
            $table->integer('department_id')->nullable()->change();
            $table->decimal('loa', 10, 2)->nullable()->change();
            $table->string('signature')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
             $table->string('employee_id')->nullable(false)->change();
            $table->integer('entiti_id')->nullable(false)->change();
            $table->integer('department_id')->nullable(false)->change();
            $table->decimal('loa', 10, 2)->nullable(false)->change();
            $table->string('signature')->nullable(false)->change();
        });
    }
};
