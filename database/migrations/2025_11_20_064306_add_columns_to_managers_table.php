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
        Schema::table('managers', function (Blueprint $table) {
            $table->integer('user_id')->after('id')->nullable()->comment('From users tabel');
            $table->integer('entiti_id')->after('user_id')->nullable()->comment('From User table');
            $table->integer('department_id')->after('entiti_id')->nullable()->comment('from department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'entiti_id', 'department_id']);
        });
    }
};
