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
            // Rename and change category_id to categories
            if (Schema::hasColumn('suppliers', 'categorei_id')) {
                $table->renameColumn('categorei_id', 'categories');
                $table->string('categories')->nullable()->change();
            }

            // Rename and change department_id to departments
            if (Schema::hasColumn('suppliers', 'department_id')) {
                $table->renameColumn('department_id', 'departments');
                $table->string('departments')->nullable()->change();
            }

            // Change status to string and nullable
            $table->string('status')->nullable()->change();

            // Rename file columns for multiple uploads
            if (Schema::hasColumn('suppliers', 'regi_certificate')) {
                $table->renameColumn('regi_certificate', 'regi_certificates');
                $table->text('regi_certificates')->nullable()->change();
            }
            if (Schema::hasColumn('suppliers', 'tax_certificate')) {
                $table->renameColumn('tax_certificate', 'tax_certificates');
                $table->text('tax_certificates')->nullable()->change();
            }
            if (Schema::hasColumn('suppliers', 'insurance_certificate')) {
                $table->renameColumn('insurance_certificate', 'insurance_certificates');
                $table->text('insurance_certificates')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Revert names and types
            if (Schema::hasColumn('suppliers', 'categories')) {
                $table->renameColumn('categories', 'categorei_id');
                $table->string('categorei_id')->nullable()->change();
            }

            if (Schema::hasColumn('suppliers', 'departments')) {
                $table->renameColumn('departments', 'department_id');
                $table->string('department_id')->nullable()->change();
            }

            $table->enum('status', ['Active', 'Suspended', 'Bad Rating', 'Inactive'])
                ->default('Active')
                ->change();

            if (Schema::hasColumn('suppliers', 'regi_certificates')) {
                $table->renameColumn('regi_certificates', 'regi_certificate');
                $table->string('regi_certificate')->nullable()->change();
            }
            if (Schema::hasColumn('suppliers', 'tax_certificates')) {
                $table->renameColumn('tax_certificates', 'tax_certificate');
                $table->string('tax_certificate')->nullable()->change();
            }
            if (Schema::hasColumn('suppliers', 'insurance_certificates')) {
                $table->renameColumn('insurance_certificates', 'insurance_certificate');
                $table->string('insurance_certificate')->nullable()->change();
            }
        });
    }
};
