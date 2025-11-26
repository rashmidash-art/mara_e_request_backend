<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'request_type.view',
                'display_name' => 'View Request Type',
                'description'  => 'Allows viewing request_type',
            ],
            [
                'name' => 'request_type.create',
                'display_name' => 'Create Request Type',
                'description'  => 'Allows creating new request_type',
            ],
            [
                'name' => 'request_type.update',
                'display_name' => 'Update Request Type',
                'description'  => 'Allows updating existing request_type',
            ],
            [
                'name' => 'request_type.approve',
                'display_name' => 'Approve Request Type',
                'description'  => 'Allows approving request_type',
            ],
            [
                'name' => 'request_type.delete',
                'display_name' => 'Delete Request Type',
                'description'  => 'Allows deleting request_type',
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

        echo "Request Type permissions seeded successfully!\n";
    }
}
