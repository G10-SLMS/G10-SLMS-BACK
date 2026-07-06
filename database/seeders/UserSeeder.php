<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $password = Hash::make('password');

        // 1 admin
        User::updateOrCreate(
            ['email' => 'admin@slms.test'],
            [
                'name' => 'System Admin',
                'password' => $password,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 3 trainers
        $trainerNames = ['Sophal Chan', 'Dara Vann', 'Kunthea Lim'];
        $trainers = collect($trainerNames)->map(function (string $name, int $i) use ($password) {
            return User::updateOrCreate(
                ['email' => 'trainer'.($i + 1).'@slms.test'],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => 'trainer',
                    'email_verified_at' => now(),
                ]
            );
        });

        // 10 students, round-robin assigned to the trainers above
        for ($i = 1; $i <= 10; $i++) {
            $trainer = $trainers[($i - 1) % $trainers->count()];

            User::updateOrCreate(
                ['email' => "student{$i}@slms.test"],
                [
                    'name' => "Student {$i}",
                    'password' => $password,
                    'role' => 'student',
                    'trainer_id' => $trainer->id,
                    'email_verified_at' => now(),
                ]
            );
        }

        User::factory()
            ->count(5)
            ->sequence(fn ($sequence) => [
                'role' => 'student',
                'trainer_id' => $trainers[$sequence->index % $trainers->count()]->id,
            ])
            ->create();
    }
}
