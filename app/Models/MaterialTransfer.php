<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\WorkSpace;

class MaterialTransfer extends Model {

    use HasFactory;

    protected $fillable = [
        'record_number',
        'record_date',
        'from_site_id',
        'to_site_id',
        'total_amount',
        'status',
        'created_by',
        'workspace_id',
        'invoice_file',
        'transport_cost',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'ledger_entry_id',
    ];

    public function items() {
        return $this->hasMany(MaterialTransferItem::class);
    }    

    public function fromSite() {
        return $this->belongsTo(Project::class, 'from_site_id');
    }

    public function toSite() {
        return $this->belongsTo(Project::class, 'to_site_id');
    }
    
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
