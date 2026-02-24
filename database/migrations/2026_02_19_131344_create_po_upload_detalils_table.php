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
        Schema::create('po_upload_detalils', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable()->comment('request table request_id');
            $table->string('is_po_created')->nullable();
            $table->string('po_number')->nullable();
            $table->string('po_date')->nullable();
            $table->decimal('po_amount', 15, 2)->nullable();
            $table->string('po_documents')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_upload_detalils');

    }
};
