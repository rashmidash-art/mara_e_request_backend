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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('bc_code')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('contact_persion_name')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('tax_id')->nullable()->change();
            $table->string('regi_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('bc_code')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('contact_persion_name')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('tax_id')->nullable(false)->change();
            $table->string('regi_no')->nullable(false)->change();
        });
    }
};
