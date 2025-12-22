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
        Schema::create('budget_codes', function (Blueprint $table) {
            $table->id();
            $table->integer('entity_id')->nullable()->comment('From Entity Table');
            $table->integer('department_id')->nullable()->comment('Freom department_table');
            $table->string('budget_code')->nullable();
            $table->string('budget_limit')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_codes');
    }
};
