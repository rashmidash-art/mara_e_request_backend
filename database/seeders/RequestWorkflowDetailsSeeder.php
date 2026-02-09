<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestWorkflowDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'requestWorkflow.view',
                'display_name' => 'View Workflow Requests',
                'description' => 'Allows viewing workflow Details requests',
            ],
            [
                'name' => 'requestWorkflow.create',
                'display_name' => 'Perform Workflow Action',
                'description' => 'Allows approve/reject/sendback',
            ],
            [
                'name' => 'requestWorkflow.action',
                'display_name' => 'Take Workflow Action',
                'description' => 'Allows approve, reject and sendback actions',
            ],
            [
                'name' => 'requestWorkflow.history',
                'display_name' => 'View Workflow History',
                'description' => 'Allows viewing workflow action history',
            ],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm['name']],
                [
                    'display_name' => $perm['display_name'],
                    'description' => $perm['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        echo "Workflow permissions seeded successfully with updated names!\n";
    }
}
