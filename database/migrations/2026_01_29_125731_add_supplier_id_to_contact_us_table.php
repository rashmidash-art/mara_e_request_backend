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
        Schema::table('supplier_ratings', function (Blueprint $table) {
            $table->string('supplier_id')->after('user_id')->nullable()->comment('from Supplier Table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_ratings', function (Blueprint $table) {
            $table->dropColumn('supplier_id');
        });
    }
};
