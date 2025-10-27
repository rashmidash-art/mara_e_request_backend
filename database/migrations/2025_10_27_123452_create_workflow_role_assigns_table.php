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
        Schema::create('workflow_role_assigns', function (Blueprint $table) {
            $table->id();
            $table->integer('workflow_id')->nullable()->comment('From Work_flows Table');
            $table->integer('step_id')->nullable()->comment('From Workflow_steps table');
            $table->integer('role_id')->nullable()->comment('From role table');
            $table->string('approval_logic')->nullable();
            $table->integer('specific_user')->default('1')->comment('1= Inactive,0=Active');
            $table->integer('user_id')->nullable()->comment('From user table');
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_role_assigns');
    }
};
