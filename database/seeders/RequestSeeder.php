<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Permission to view requests
        DB::table('permissions')->updateOrInsert(
            ['name' => 'requests.view'],
            [
                'display_name' => 'View Requests',
                'description'  => 'Allows viewing requests',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // Permission to create requests
        DB::table('permissions')->updateOrInsert(
            ['name' => 'requests.create'],
            [
                'display_name' => 'Create Requests',
                'description'  => 'Allows creating new requests',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // Permission to approve requests
        DB::table('permissions')->updateOrInsert(
            ['name' => 'requests.approve'],
            [
                'display_name' => 'Approve Requests',
                'description'  => 'Allows approving requests',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // Permission to delete requests
        DB::table('permissions')->updateOrInsert(
            ['name' => 'requests.delete'],
            [
                'display_name' => 'Delete Requests',
                'description'  => 'Allows deleting requests',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        echo "✅ Request permissions seeded successfully!\n";
    }
}
