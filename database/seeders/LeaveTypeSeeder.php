<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $types = [
            [
                'name' => 'Sick Leave',
                'code' => 'sick',
                'description' => 'Leave for illness or medical appointments.',
                'max_days_per_year' => 10,
                'requires_attachment' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Personal Leave',
                'code' => 'personal',
                'description' => 'Planned personal time off.',
                'max_days_per_year' => 15,
                'requires_attachment' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Emergency Leave',
                'code' => 'emergency',
                'description' => 'Urgent, unplanned personal or family matters.',
                'max_days_per_year' => 5,
                'requires_attachment' => false,
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}