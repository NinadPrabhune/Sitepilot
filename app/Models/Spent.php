<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\WorkSpace;

class Spent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'spent_ledger_id',
        'amount',
        'project_id',
        'workspace_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function spentLedger()
    {
        return $this->belongsTo(SpentLedger::class, 'spent_ledger_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
