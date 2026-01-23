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
        Schema::create('request_details_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('request_details_id')->nullable()->comment('request workflow details table id');
            $table->string('is_po_created')->nullable();
            $table->string('po_number')->nullable();
            $table->string('po_date')->nullable();
            $table->string('po_documents')->nullable();
            $table->string('is_delivery_completed')->nullable();
            $table->string('delivery_completed_number')->nullable();
            $table->string('delivery_completed_date')->nullable();
            $table->string('delivery_completed_documents')->nullable();
            $table->string('is_payment_completed')->nullable();
            $table->string('payment_completed_number')->nullable();
            $table->string('payment_completed_date')->nullable();
            $table->string('payment_completed_documents')->nullable();
            $table->string('status')->default('Yes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_details_documents');
    }
};
