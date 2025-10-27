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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->integer('workflow_id')->nullable()->comment('form Workflow table');
            $table->string('name')->nullable();
            $table->string('form_type')->nullable();
            $table->integer('sla_hour')->nullable();
            $table->text('description')->nullable();
            $table->string('escalation')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
