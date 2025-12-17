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
        Schema::create('workflow_users', function (Blueprint $table) {
            $table->id();
            $table->integer('entity_id')->nullable()->comment('from Entity table');
            $table->integer('workflow_id')->nullable()->comment('from workflow table');
            $table->integer('step_id')->nullable()->comment('from Workflow_step table');
            $table->integer('role_id')->nullable()->comment('from workflow_role table');
            $table->string('logic')->nullable()->default('single')->comment('signle=any user belongs to the role,or=assigned user ,and= All user of that assigned role');
            $table->integer('user_id')->nullable()->comment('from user table');            $table->string('status')->nullable()->comment('active,inactive');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_users');
    }
};
