<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EntityRequestTypePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'entityReqyestType.view',
                'display_name' => 'View Entity Request Types',
                'description' => 'Allows viewing entity request type mappings',
            ],
            [
                'name' => 'entityReqyestType.create',
                'display_name' => 'Create Entity Request Types',
                'description' => 'Allows creating entity & category request type mappings',
            ],
            [
                'name' => 'entityReqyestType.update',
                'display_name' => 'Update Entity Request Types',
                'description' => 'Allows updating entity request type mappings',
            ],
            [
                'name' => 'entityReqyestType.delete',
                'display_name' => 'Delete Entity Request Types',
                'description' => 'Allows deleting entity request type mappings',
            ],
            [
                'name' => 'entityReqyestType.group-delete',
                'display_name' => 'Group Delete Entity Request Types',
                'description' => 'Allows deleting all request types for an entity and category',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'description'  => $permission['description'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }

        echo "Entity Request Type permissions seeded successfully!\n";
    }
}
