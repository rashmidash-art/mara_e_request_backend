<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeleteRequestPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = ['requests.view', 'requests.create', 'requests.approve', 'requests.delete'];

        // Remove from pivot table first (if you use role_permission pivot)
        DB::table('permission_role')
            ->whereIn('permission_id', function ($query) use ($permissions) {
                $query->select('id')->from('permissions')->whereIn('name', $permissions);
            })->delete();

        // Delete from permissions table
        DB::table('permissions')->whereIn('name', $permissions)->delete();

        echo "✅ Request permissions deleted successfully!\n";
    }
}
