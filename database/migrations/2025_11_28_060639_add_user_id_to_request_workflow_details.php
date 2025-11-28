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
        Schema::table('request_workflow_details', function (Blueprint $table) {
            $table->integer('assigned_user_id')->nullable()->after('workflow_role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_workflow_details', function (Blueprint $table) {
            $table->dropColumn('assigned_user_id');
        });
    }
};
