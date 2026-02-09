<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RollbackRequestWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('permissions')->whereIn('name', [
            'request-workflow.view',
            'request-workflow.create',
            'request-workflow.action',
            'request-workflow.history',
        ])->delete();

        echo "Workflow permissions rolled back successfully!\n";
    }
}
