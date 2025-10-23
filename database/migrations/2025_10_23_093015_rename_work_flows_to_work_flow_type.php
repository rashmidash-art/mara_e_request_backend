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
        Schema::table('work_flows', function (Blueprint $table) {
            Schema::rename('work_flows', 'work_flow_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_flows', function (Blueprint $table) {
            Schema::rename('work_flow_types', 'work_flows');
        });
    }
};
