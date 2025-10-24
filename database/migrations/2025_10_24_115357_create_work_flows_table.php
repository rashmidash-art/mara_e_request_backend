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
        Schema::create('work_flows', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_id');
            $table->integer('categori_id')->nullable()->comment('From Categori Table');
            $table->string('name')->nullable();
            $table->integer('steps')->nullable();
            $table->integer('status')->default(0)->comment('0=Active 1=Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_flows');
    }
};
