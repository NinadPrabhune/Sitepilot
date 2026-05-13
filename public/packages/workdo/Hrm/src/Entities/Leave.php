<?php

namespace Workdo\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\WorkSpace;
use Workdo\Taskly\Entities\Project;

class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'user_id',
        'Leave_type_id',
        'applied_on',
        'start_date',
        'end_date',
        'total_leave_days',
        'approved_days',
        'leave_reason',
        'remark',
        'status',
        'workspace',
        'site_id',
        'created_by',
        'status_reason',
    ];

    protected static function newFactory()
    {
        return \Workdo\Hrm\Database\factories\LeaveFactory::new();
    }

    public static function getLeaveType($leave_type)
    {
        $leavetype = LeaveType::where('id', '=', $leave_type)->first();
        $leavetype = !empty($leavetype) ? $leavetype : null;
        return $leavetype;
    }

    public function leaveType()
    {
        return $this->hasOne('Workdo\Hrm\Entities\LeaveType', 'id', 'leave_type_id');
    }

    // public function EmployeeName()
    // {
    //     return $this->hasOne('Workdo\Hrm\Entities\Employee', 'id', 'employee_id');
    // }

    public function EmployeeName()
    {
        return $this->hasOne('Workdo\Hrm\Entities\Employee', 'user_id', 'user_id');
    }
    
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace');
    }

   
    public function site()
    {
        return $this->belongsTo(Project::class, 'site_id', 'id');
    }

}
