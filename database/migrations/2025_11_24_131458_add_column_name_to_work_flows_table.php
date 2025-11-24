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
            $table->integer('request_type_id')->nullable()->after('categori_id')->comment('From Request type Table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_flows', function (Blueprint $table) {
            $table->dropColumn('request_type_id');
        });
    }
};
