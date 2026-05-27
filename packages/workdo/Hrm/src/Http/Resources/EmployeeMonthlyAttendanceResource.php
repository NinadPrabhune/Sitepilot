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
        return [
            'employee_id' => $this->employee_id,
            'employee_name' => $this->name,
            'present_days' => (int) $this->present_days,
            'leave_days' => (int) $this->leave_days,
            'absent_days' => (int) $this->absent_days,
            'late_count' => (int) $this->late_count,
            'late_hours' => $this->late_hours,
            'early_leaving_count' => (int) $this->early_leaving_count,
            'early_leaving_hours' => $this->early_leaving_hours,
            'overtime_hours' => $this->overtime_hours,
            'attendance' => $this->attendance,
        ];
    }
}