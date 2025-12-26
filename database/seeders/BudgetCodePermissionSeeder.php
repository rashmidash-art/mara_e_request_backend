<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BudgetCodePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'budget-code.view',
                'display_name' => 'View Budget Codes',
                'description' => 'Allows viewing budget code list',
            ],
            [
                'name' => 'budget-code.preview',
                'display_name' => 'Preview Budget Code',
                'description' => 'Allows previewing auto-generated budget code',
            ],
            [
                'name' => 'budget-code.create',
                'display_name' => 'Create Budget Code',
                'description' => 'Allows creating new budget codes',
            ],
            [
                'name' => 'budget-code.generate',
                'display_name' => 'Generate Budget Code',
                'description' => 'Allows generating next budget code sequence',
            ],
            [
                'name' => 'budget-code.update',
                'display_name' => 'Update Budget Code',
                'description' => 'Allows updating existing budget codes',
            ],
            [
                'name' => 'budget-code.delete',
                'display_name' => 'Delete Budget Code',
                'description' => 'Allows deleting budget codes',
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

        echo "Budget Code permissions seeded successfully!\n";
    }
}
