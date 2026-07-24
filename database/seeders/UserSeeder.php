<?php

namespace Database\Seeders;

use App\Models\Avatar;
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

        // 3 educators
        $educatorNames = ['Sophal Chan', 'Dara Vann', 'Kunthea Lim'];
        $educators = collect($educatorNames)->map(function (string $name, int $i) use ($password) {
            return User::updateOrCreate(
                ['email' => 'educator'.($i + 1).'@slms.test'],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => 'educator',
                    'phone' => '01234567'.$i,
                    'email_verified_at' => now(),
                ]
            );
        });

        User::updateOrCreate(
            ['email' => 'demo.student@student.passerellesnumeriques.org'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'educator_id' => $educators[0]->id,
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
                'educator_id' => $educators[0]->id,
                'phone' => '012000002',
                'class_name' => 'Web B2C1',
                'generation' => '2026',
                'province' => 'Phnom Penh',
                'gender' => 'female',
                'email_verified_at' => now(),
            ]
        );

        for ($i = 1; $i <= 10; $i++) {
            $educator = $educators[($i - 1) % $educators->count()];

            User::updateOrCreate(
                ['email' => "student{$i}@slms.test"],
                [
                    'name' => "Student {$i}",
                    'password' => $password,
                    'role' => 'student',
                    'educator_id' => $educator->id,
                    'phone' => '09' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                    'class_name' => 'Web 2026B'.(($i % 3) + 1),
                    'generation' => '2026',
                    'province' => $provinces[$i % count($provinces)],
                    'gender' => $genders[$i % 2],
                    'email_verified_at' => now(),
                ]
            );
        }

        User::factory()
            ->count(5)
            ->sequence(fn ($sequence) => [
                'role' => 'student',
                'educator_id' => $educators[$sequence->index % $educators->count()]->id,
                'phone' => '08' . str_pad((string) $sequence->index, 7, '0', STR_PAD_LEFT),
                'class_name' => 'Web B2C'.(($sequence->index % 3) + 1),
                'generation' => '2026',
                'province' => $provinces[$sequence->index % count($provinces)],
                'gender' => $genders[$sequence->index % 2],
            ])
            ->create();

        User::whereNull('avatar_id')->get()->each(function (User $user) {
            $avatar = Avatar::fallbackFor($user->gender);

            if ($avatar) {
                $user->avatar_id = $avatar->id;
                $user->save();
            }
        });
    }
}
