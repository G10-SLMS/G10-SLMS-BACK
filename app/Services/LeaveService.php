<?php

namespace App\Services;

use App\Models\LeaveRequest;

class LeaveService
{

    public function __construct()
    {
        //
    }

    public function normalizeDuration(array $data): array
    {
        if (($data['duration_type'] ?? null) === 'hourly') {
            if (!empty($data['start_time']) && !empty($data['end_time'])) {
                $data['duration_hours'] = LeaveRequest::calculateHoursFromTimes(
                    $data['start_time'],
                    $data['end_time'],
                );
            }
            $data['end_date'] = $data['start_date'] ?? ($data['end_date'] ?? null);
        } else {
            $data['duration_hours'] = null;
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }
}