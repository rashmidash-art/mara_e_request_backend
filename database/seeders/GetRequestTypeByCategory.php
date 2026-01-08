<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GetRequestTypeByCategory extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'workflowbyTypeandCats.view',
                'display_name' => 'View Request-type by category',
                'description' => 'Allows viewing Request-type by category list',
            ],
            [
                'name' => 'workflowbyTypeandCats.create',
                'display_name' => 'Create Request-type by category',
                'description' => 'Allows creating new Request-type by categorys',
            ],
            [
                'name' => 'workflowbyTypeandCats.update',
                'display_name' => 'Update Request-type by category',
                'description' => 'Allows updating existing Request-type by categorys',
            ],
            [
                'name' => 'workflowbyTypeandCats.delete',
                'display_name' => 'Delete Request-type by category',
                'description' => 'Allows deleting Request-type by categorys',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'description' => $permission['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        echo "Request-type by category permissions seeded successfully!\n";
    }
}
