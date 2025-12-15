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
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->integer('workflow_id')->nullable()->comment('From Workflow table');
            $table->integer('step_id')->nullable()->comment('Workkflow step table');
            $table->integer('role_id')->nullable()->comment('workflow role table');
            $table->integer('user_id')->nullable()->comment('From wokflow role asigend user');
            $table->text('description')->nullable();
            $table->integer('enable_rule')->nullable();
            $table->integer('enable_notification')->nullable();
            $table->integer('enable_mail');
            $table->integer('notify_type')->nullable()->default('1')->comment('0=Reassign,1=Notify,2=notify & reassign');
            $table->string('sla_hour')->nullable();
            $table->string('escalation_hour')->nullable();
            $table->string('status')->default('Active')->comment('1=Active , 0= Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
