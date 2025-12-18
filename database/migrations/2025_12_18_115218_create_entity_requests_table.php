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
        Schema::create('entity_requests', function (Blueprint $table) {
            $table->id();
            $table->integer('entity_id')->nullable()->comment('from entity table');
            $table->integer('categore_id')->nullable()->comment('from category table');
            $table->integer('request_type_id')->nullable()->comment('from request_type Table');
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_requests');
    }
};
