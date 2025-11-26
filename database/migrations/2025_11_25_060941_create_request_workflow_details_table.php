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
        Schema::create('request_workflow_details', function (Blueprint $table) {
            $table->id();

            $table->string('request_id')->nullable()->comment('From Request table');
            $table->string('workflow_id')->nullable()->comment('From Workflow table');
            $table->integer('workflow_step_id')->nullable()->comment('From Workflow Step table');
            $table->integer('workflow_role_id')->nullable()->comment('From Workflow Role table');
            $table->integer('action_taken_by')->nullable()->comment('User ID');
            $table->text('remark')->nullable();
            $table->string('status')->nullable();
            $table->string('is_sendback')->nullable();
            $table->text('sendback_remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_workflow_details');
    }
};
