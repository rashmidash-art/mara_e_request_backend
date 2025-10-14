<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call(class: AdminSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RemoveEntityPermissionsSeeder::class);
        $this->call(BudgetSeeder::class);

    }
}
