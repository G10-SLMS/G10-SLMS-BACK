<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default admin user
        User::create([
            'name' => 'Sim Hul',
            'gender' => 'male',
            'email' => 'sim.hul@passerellesnumeriques.org',
            'password' => Hash::make('simhul@123'),
            'role' => 'admin', 
        ]);
    }
}
