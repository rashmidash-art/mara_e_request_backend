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
            $table->string('approval_logic')->after(column: 'workflow_step_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_workflow_details', function (Blueprint $table) {
            $table->dropColumn( 'approval_logic');

        });
    }
};
