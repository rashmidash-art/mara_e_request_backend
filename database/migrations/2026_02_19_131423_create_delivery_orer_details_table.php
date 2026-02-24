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
        Schema::create('delivery_orer_details', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable()->comment('request table request_id');
            $table->string('is_delivery_completed')->nullable();
            $table->string('delivery_number')->nullable();
            $table->string('delivery_date')->nullable();
            $table->integer('delivery_quantity')->nullable();
            $table->string('delivery_documents')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_orer_details');
    }
};
