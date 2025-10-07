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
            $table->string('employee_id')->after('email');
            $table->integer('entiti_id')->after('employee_id');
            $table->integer('department_id')->after('entiti_id')->comment('entiti table');
            $table->decimal('loa',10,2)->after('department_id')->comment('department_table');
            $table->string('signature')->after('loa');
            $table->enum('status', ['Active','Inactive','Away'])->default('Active')->after('signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employee_id','entiti_id','department_id','loa','signature','status');
        });
    }
};
