<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AddEntityViewPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate(
            ['name' => 'entities.view'],
            [
                'display_name' => 'View Entities',
                'description'  => 'Allows viewing own entity details only.',
            ]
        );

        echo "Permission entities.view created successfully!" . PHP_EOL;
    }
}
