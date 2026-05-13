<?php

namespace Workdo\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Workdo\Taskly\Entities\Project;

class Attendance extends Model {

    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'status',
        'clock_in',
        'clock_out',
        'late',
        'early_leaving',
        'overtime',
        'total_rest',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_in_image',
        'clock_out_image',
        'workspace',
        'site_id',
        'created_by',
    ];

    protected static function newFactory() {
        return \Workdo\Hrm\Database\factories\AttendanceFactory::new();
    }

    public function employees() {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // Relationship to Project/Site
    public function site() {
        return $this->belongsTo(Project::class, 'site_id', 'id');
    }
}
