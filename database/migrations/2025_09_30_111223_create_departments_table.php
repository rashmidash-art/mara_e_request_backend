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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->integer('entiti_id')->comment('entity table');
            $table->integer('manager_id')->comment('manager table');
            $table->string('name');
            $table->string('department_code');
            $table->string('bc_dimention_value');
            $table->integer('enable_cost_center')->default(0)->comment('0=Active, 1= InActive');
            $table->integer('work_flow_id')->comment('workflow table');
            $table->decimal('budget',10,2)->default(0);
            $table->integer('status')->default('0')->comment('0=Active, 1= Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
