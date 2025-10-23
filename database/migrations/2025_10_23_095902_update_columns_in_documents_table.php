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
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('work_flow_id', 'work_flow_type_id');
            $table->renameColumn('role_id', 'roles');
            $table->renameColumn('fileformat_id', 'file_formats');
            $table->renameColumn('categorie_id', 'categories');
            $table->integer('entiti_id')->nullable()->after('name')->comment('Entity table');
            $table->string('status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('work_flow_type_id', 'work_flow_id');
            $table->renameColumn('roles', 'role_id');
            $table->renameColumn('file_formats', 'fileformat_id');
            $table->renameColumn('categories', 'categorie_id');
            $table->dropColumn('entiti_id');
            $table->integer('status')->default(0)->comment('0=Active 1=Inactive')->change();
        });
    }
};
