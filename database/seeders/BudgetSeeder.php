<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'budgets.view'],
            [
                'display_name' => 'View Budgets',
                'description'  => 'Allows viewing budgets',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        DB::table('permissions')->updateOrInsert(
            ['name' => 'budgets.allocate'],
            [
                'display_name' => 'Allocate Budgets',
                'description'  => 'Allows allocating budgets to departments',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        echo "✅ Budget permissions seeded successfully!\n";
    }
}
