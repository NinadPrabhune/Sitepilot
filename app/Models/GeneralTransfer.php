<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Workdo\Taskly\Entities\Project;
class GeneralTransfer extends Model
{
    use HasFactory;

    // Table name (since it's not the default plural form)
    protected $table = 'general_transfer';

    // Mass assignable fields
    protected $fillable = [
        'transfer_type',
        'machinery_id',
        'tools_and_equipment_id',
        'employee_id',
        'transfer_date',
        'transfer_date_end',
        'transfer_qty',
        'from_site_id',
        'to_site_id',
        'created_by',
        'workspace_id',
        'transport_cost',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'ledger_entry_id',
        'operational_status',
        'status',
    ];
    
    protected $casts = [ 'transfer_date' => 'date', 'transfer_date_end' => 'date', ];

    /**
     * Relationships
     */

    // Machinery relation
    public function machinery()
    {
        return $this->belongsTo(Machinery::class, 'machinery_id');
    }

    // Tools & Equipment relation
    public function toolsAndEquipment()
    {
        return $this->belongsTo(AssetsToolsAndEquipment::class, 'tools_and_equipment_id');
    }

    // Employee relation
    public function employee()
    {
        return $this->belongsTo(\Workdo\Hrm\Entities\Employee::class, 'employee_id', 'user_id');
    }

    public function fromSite() {
        return $this->belongsTo(Project::class, 'from_site_id');
    }

    public function toSite() {
        return $this->belongsTo(Project::class, 'to_site_id');
    }

    // Creator relation
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ledgerEntry()
    {
        return $this->belongsTo(\App\Domain\Machinery\Models\MachineryLedger::class, 'ledger_entry_id');
    }
}
