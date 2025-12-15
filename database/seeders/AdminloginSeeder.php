<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminloginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Admin',
            'email' => 'admin@maracorp.com',
            'password' => Hash::make('Admin@123'),
            'user_type' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
