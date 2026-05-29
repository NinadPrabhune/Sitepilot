<?php

namespace Workdo\Hrm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee Monthly Attendance Resource
 *
 * Transforms monthly attendance data for an employee for mobile API responses.
 *
 * @example
 * {
 *   "employee_id": 5,
 *   "employee_name": "John Doe",
 *   "present_days": 22,
 *   "leave_days": 3,
 *   "absent_days": 5,
 *   "late_count": 2,
 *   "late_hours": 1.5,
 *   "early_leaving_count": 1,
 *   "early_leaving_hours": 0.5,
 *   "overtime_hours": 3.0,
 *   "attendance": {
 *     "01": "P",
 *     "02": "P",
 *     "03": "L",
 *     "04": "A"
 *   }
 * }
 */
class EmployeeMonthlyAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        return [
            'employee_id' => $data['employee_id'] ?? null,
            'employee_name' => $data['name'] ?? '',
            'present_days' => (int) ($data['present_days'] ?? 0),
            'leave_days' => (int) ($data['leave_days'] ?? 0),
            'absent_days' => (int) ($data['absent_days'] ?? 0),
            'late_count' => (int) ($data['late_count'] ?? 0),
            'late_hours' => $data['late_hours'] ?? 0,
            'early_leaving_count' => (int) ($data['early_leaving_count'] ?? 0),
            'early_leaving_hours' => $data['early_leaving_hours'] ?? 0,
            'overtime_hours' => $data['overtime_hours'] ?? 0,
            'attendance' => (object) ($data['attendance'] ?? []),
        ];
    }
}