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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable();
            $table->string('entiti')->nullable()->comment('From Entiti table');
            $table->integer('user')->nullable()->comment('From Users Table');
            $table->integer('request_type')->nullable()->comment('From Request Type Table');
            $table->integer('category')->nullable()->comment('from Category Table');
            $table->integer('department')->nullable()->comment('From Department Table');
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->integer('supplier-id')->nullable()->comment('From Supplier Table');
            $table->date('expected_date')->nullable();
            $table->string('priority')->nullable();
            $table->integer('behalf_of')->default(0)->comment('0=No , 1= Yes');
            $table->integer('behalf_of_department')->nullable();
            $table->text('business_justification')->nullable();
            $table->enum('status', ['submitted', 'draft', 'deleted'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
