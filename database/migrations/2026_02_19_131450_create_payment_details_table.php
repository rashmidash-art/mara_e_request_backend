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
        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable()->comment('request table request_id');
            $table->string('is_payment_completed')->nullable();
            $table->string('payment_number')->nullable();
            $table->string('payment_date')->nullable();
            $table->string('payment_documents')->nullable();
             $table->decimal('payment_amount', 15, 2)->nullable();
             $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_details');
    }
};
