<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: LeaveRequestSeeder depends on users + leave types
     * already existing, and CommentSeeder depends on leave requests.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LeaveTypeSeeder::class,
            LeaveRequestSeeder::class,
            CommentSeeder::class,
        ]);
    }
}
