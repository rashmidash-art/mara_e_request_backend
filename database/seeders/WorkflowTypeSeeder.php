<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Prepare data
        $data = [
            [
                'id' => 1,
                'name' => 'Inter Departmental',
                'status' => 0,
                'created_at' => '2025-10-03 11:10:07',
                'updated_at' => '2025-10-03 11:12:01',
            ],
            [
                'id' => 2,
                'name' => 'Intra Departmental',
                'status' => 0,
                'created_at' => '2025-10-03 11:11:07',
                'updated_at' => '2025-10-03 11:39:36',
            ],
        ];

        // Insert into database
        DB::table('work_flow_types')->insert($data);
    }
}
