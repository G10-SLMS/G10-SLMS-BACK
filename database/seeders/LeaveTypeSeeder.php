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
            ],
            [
                'name' => 'Annual Leave',
                'code' => 'annual',
                'description' => 'Planned personal time off.',
                'max_days_per_year' => 15,
                'requires_attachment' => false,
            ],
            [
                'name' => 'Family Emergency',
                'code' => 'family_emergency',
                'description' => 'Urgent, unplanned family matters.',
                'max_days_per_year' => 5,
                'requires_attachment' => false,
            ],
            [
                'name' => 'Bereavement Leave',
                'code' => 'bereavement',
                'description' => 'Leave following the death of a close family member.',
                'max_days_per_year' => 5,
                'requires_attachment' => false,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
