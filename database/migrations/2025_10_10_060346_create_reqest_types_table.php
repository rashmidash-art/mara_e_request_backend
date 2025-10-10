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
        Schema::create('reqest_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category_id')->nullable()->comment('From Category Table');
            $table->string('document_id')->nullable()->comment('From Document Table');
            $table->string('bc_code')->nullable();
            $table->integer('no_of_days')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reqest_types');
    }
};
