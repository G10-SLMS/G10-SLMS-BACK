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
        // Create default admin user
        User::create([
            'name' => 'Sim Hul',
            'gender' => 'male',
            'email' => 'sim.hul@passerellesnumeriques.org',
            'password' => Hash::make('simhul@123'),
            'role' => 'admin',
        ]);
        $password = Hash::make('password');
        $provinces = ['Phnom Penh', 'Siem Reap', 'Battambang', 'Kampong Cham', 'Kandal'];
        $genders = ['male', 'female'];

        // 1 admin
        User::updateOrCreate(
            ['email' => 'admin@slms.test'],
            [
                'name' => 'System Admin',
                'password' => $password,
                'role' => 'admin',
                'phone' => '012345678',
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
                    'phone' => '01234567'.$i,
                    'email_verified_at' => now(),
                ]
            );
        });

        // Demo accounts used by the login page's quick-fill buttons.
        // Both are role=student — "fellow" is just a different email
        // domain convention (student.* vs fellow.*), not a separate role.
        User::updateOrCreate(
            ['email' => 'demo.student@student.passerellesnumeriques.org'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'trainer_id' => $trainers[0]->id,
                'phone' => '012000001',
                'class_name' => 'Web B2C1',
                'generation' => '2026',
                'province' => 'Phnom Penh',
                'gender' => 'male',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'demo.fellow@fellow.passerellesnumeriques.org'],
            [
                'name' => 'Demo Fellow',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'trainer_id' => $trainers[0]->id,
                'phone' => '012000002',
                'class_name' => 'Web B2C1',
                'generation' => '2026',
                'province' => 'Phnom Penh',
                'gender' => 'female',
                'email_verified_at' => now(),
            ]
        );

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
                    'phone' => '09' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                    'class_name' => 'Web 2026B'.(($i % 3) + 1),
                    'generation' => '2026',
                    'province' => $provinces[$i % count($provinces)],
                    'gender' => $genders[$i % 2],
                    'email_verified_at' => now(),
                ]
            );
        }

        // A handful of extra random students via the factory, spread across trainers
        User::factory()
            ->count(5)
            ->sequence(fn ($sequence) => [
                'role' => 'student',
                'trainer_id' => $trainers[$sequence->index % $trainers->count()]->id,
                'phone' => '08' . str_pad((string) $sequence->index, 7, '0', STR_PAD_LEFT),
                'class_name' => 'Web B2C'.(($sequence->index % 3) + 1),
                'generation' => '2026',
                'province' => $provinces[$sequence->index % count($provinces)],
                'gender' => $genders[$sequence->index % 2],
            ])
            ->create();
    }
}
