<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Rename only if the old column exists
            if (Schema::hasColumn('documents', 'work_flow_id')) {
                $table->renameColumn('work_flow_id', 'work_flow_type_id');
            }

            if (Schema::hasColumn('documents', 'role_id')) {
                $table->renameColumn('role_id', 'roles');
            }

            if (Schema::hasColumn('documents', 'fileformat_id')) {
                $table->renameColumn('fileformat_id', 'file_formats');
            }

            if (Schema::hasColumn('documents', 'categorie_id')) {
                $table->renameColumn('categorie_id', 'categories');
            }

            // Add entiti_id if not present
            if (!Schema::hasColumn('documents', 'entiti_id')) {
                $table->integer('entiti_id')->nullable()->after('name')->comment('Entity table');
            }

            // Change status to string only if exists
            if (Schema::hasColumn('documents', 'status')) {
                $table->string('status')->nullable()->change();
            }

            if (Schema::hasColumn('documents', 'work_flow_type_id')) {
                 $table->renameColumn('work_flow_type_id', 'work_flow_steps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'work_flow_type_id')) {
                $table->renameColumn('work_flow_type_id', 'work_flow_id');
            }

            if (Schema::hasColumn('documents', 'roles')) {
                $table->renameColumn('roles', 'role_id');
            }

            if (Schema::hasColumn('documents', 'file_formats')) {
                $table->renameColumn('file_formats', 'fileformat_id');
            }

            if (Schema::hasColumn('documents', 'categories')) {
                $table->renameColumn('categories', 'categorie_id');
            }

            if (Schema::hasColumn('documents', 'entiti_id')) {
                $table->dropColumn('entiti_id');
            }

            if (Schema::hasColumn('documents', 'status')) {
                $table->integer('status')->default(0)->comment('0=Active 1=Inactive')->change();
            }

            if (Schema::hasColumn('documents', 'work_flow_steps')) {
                $table->renameColumn('work_flow_steps','work_flow_type_id');
            }
        });
    }
};
