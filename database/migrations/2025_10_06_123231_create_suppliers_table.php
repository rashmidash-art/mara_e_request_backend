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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bc_code');
            $table->string('email');
            $table->string('phone');
            $table->string('contact_persion_name');
            $table->text('address');
            $table->string('tax_id');
            $table->string('regi_no');
            $table->integer('categorei_id')->comment('category Table');
            $table->integer('department_id')->comment('department table');
            $table->string('regi_certificate')->nullable();
            $table->string('tax_certificate')->nullable();
            $table->string('insurance_certificate')->nullable();
            $table->integer('status')->default(0)->comment('0=Active, 1=Susspended ,2=Bad Rating, 3=Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
