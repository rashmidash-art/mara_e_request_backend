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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('work_flow_id')->comment('Work flow table');
            $table->string('role_id')->comment('Role table');
            $table->string('fileformat_id')->comment('FileFormat table');
            $table->string('categorie_id')->comment('category table');
            $table->integer('max_count');
            $table->integer('expiry_days');
            $table->text('description');
            $table->integer('status')->default(0)->comment('0=Active 1=Inactive');
            $table->integer('is_mandatory')->default(0)->comment('0=Active , 1=Inactive');
            $table->integer('is_enable')->default(0)->comment('0=Active , 1=Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
