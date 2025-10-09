<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            'entities',
            'work-flows',
            'managers',
            'department',
            'users',
            'categore',
            'supplier',
            'fileformat',
            'document',
            'roles', // Add this
        ];

        $actions = [
            'view'   => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
        ];

        foreach ($modules as $module) {
            foreach ($actions as $actionKey => $actionLabel) {
                $permissionName = "{$module}.{$actionKey}";

                Permission::firstOrCreate(
                    ['name' => $permissionName],
                    [
                        'display_name' => "{$actionLabel} " . ucfirst(str_replace('-', ' ', $module)),
                        'description'  => "Allows the user to {$actionLabel} records in {$module}.",
                    ]
                );
            }
        }


        $extraPermissions = [
            'roles.assign' => [
                'display_name' => 'Assign Role',
                'description'  => 'Allows assigning roles to users.',
            ],
            'roles.remove' => [
                'display_name' => 'Remove Role',
                'description'  => 'Allows removing roles from users.',
            ],
        ];

        foreach ($extraPermissions as $name => $meta) {
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $meta['display_name'],
                    'description'  => $meta['description'],
                ]
            );
        }

        echo "✅ Permissions seeded successfully!\n";
    }
}
