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
        Schema::table('request_details_documents', function (Blueprint $table) {
            $table->decimal('po_amount', 15, 2)->nullable()->after('po_number');

            $table->integer('delivery_quantity')->nullable()->after('delivery_completed_number');

            $table->decimal('payment_amount', 15, 2)->nullable()->after('payment_completed_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_details_documents', function (Blueprint $table) {
             $table->dropColumn('po_amount','delivery_quantity','payment_amount');
        });
    }
};
