<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestCreateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'requests.view',
                'display_name' => 'View Requests',
                'description'  => 'Allows viewing requests',
            ],
            [
                'name' => 'requests.create',
                'display_name' => 'Create Requests',
                'description'  => 'Allows creating new requests',
            ],
            [
                'name' => 'requests.update',
                'display_name' => 'Update Requests',
                'description'  => 'Allows updating existing requests',
            ],
            [
                'name' => 'requests.approve',
                'display_name' => 'Approve Requests',
                'description'  => 'Allows approving requests',
            ],
            [
                'name' => 'requests.delete',
                'display_name' => 'Delete Requests',
                'description'  => 'Allows deleting requests',
            ],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm['name']],
                [
                    'display_name' => $perm['display_name'],
                    'description'  => $perm['description'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }

        echo "✅ Request permissions seeded successfully!\n";
    }
}
