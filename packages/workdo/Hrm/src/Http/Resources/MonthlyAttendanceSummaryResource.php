<?php

namespace Workdo\Hrm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
  * Monthly Attendance Summary Resource
  *
  * Transforms monthly attendance summary for mobile API responses.
  *
  * @example
  * {
  *   "month": "05",
  *   "year": "2024",
  *   "month_display": "May-2024",
  *   "working_days": 31,
  *   "total_present": 650,
  *   "total_leave": 85,
  *   "total_absent": 100,
  *   "leave_dates": ["03", "05", "15"],
  *   "absent_dates": ["04", "07", "12"],
  *   "total_late_count": 45,
  *   "total_late_hours": 35.5,
  *   "total_early_leaving_count": 20,
  *   "total_early_leaving_hours": 15.0,
  *   "total_overtime_hours": 42.5,
  *   "average_attendance": 91.67,
  *   "dates": ["01", "02", "03", "...", "31"]
  * }
  */
class MonthlyAttendanceSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->month,
            'year' => $this->year,
            'month_display' => $this->month_display,
            'working_days' => (int) $this->working_days,
            'total_present' => (int) ($this->total_present ?? 0),
            'total_leave' => (int) ($this->total_leave ?? 0),
            'total_absent' => (int) ($this->total_absent ?? 0),
            'leave_dates' => $this->leave_dates ?? [],
            'absent_dates' => $this->absent_dates ?? [],
            'total_late_count' => (int) ($this->total_late_count ?? 0),
            'total_late_hours' => $this->total_late_hours ?? 0,
            'total_early_leaving_count' => (int) ($this->total_early_leaving_count ?? 0),
            'total_early_leaving_hours' => $this->total_early_leaving_hours ?? 0,
            'total_overtime_hours' => $this->total_overtime_hours ?? 0,
            'average_attendance' => $this->average_attendance ?? 0,
            'dates' => $this->dates ?? [],
        ];
    }
}