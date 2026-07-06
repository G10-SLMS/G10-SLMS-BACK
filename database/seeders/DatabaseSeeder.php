<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            // LeaveTypeSeeder::class,   // enable once the leave_types table has real columns
            // LeaveRequestSeeder::class, // enable once the leave_requests table has real columns
            // CommentSeeder::class,      // enable once the comments table has real columns
        ]);
    }
}