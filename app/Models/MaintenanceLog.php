<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_entry_id',
        'machinery_id',
        'vendor_id',
        'maintenance_date',
        'cost',
        'paid_by',
        'description',
        'attachment',
        'site_id',
        'workspace_id',
        'created_by',
        'status',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'cost' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($maintenance) {
            // Hard link validation: Maintenance cannot exist without machinery
            if (!$maintenance->machinery_id) {
                throw new \RuntimeException('Maintenance cannot be created without machinery. Machinery ID is required.');
            }
            
            // Validate machinery exists
            $machinery = Machinery::find($maintenance->machinery_id);
            if (!$machinery) {
                throw new \RuntimeException('Invalid machinery ID. Machinery does not exist.');
            }
        });

        static::updating(function ($maintenance) {
            // Allow approval workflow to set ledger_entry_id
            $isSettingLedgerEntry = in_array('ledger_entry_id', array_keys($maintenance->getDirty())) && 
                                   $maintenance->getOriginal('ledger_entry_id') === null;
            
            // Block edits if ledger entry already exists AND this is not the initial approval
            if ($maintenance->getOriginal('ledger_entry_id') && !$isSettingLedgerEntry) {
                throw new \RuntimeException('Cannot edit maintenance after ledger entry has been created. Use reversal instead.');
            }
        });
    }

    public function machinery()
    {
        return $this->belongsTo(Machinery::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function site()
    {
        return $this->belongsTo(Project::class, 'site_id');
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerEntry()
    {
        return $this->belongsTo(\App\Domain\Machinery\Models\MachineryLedger::class, 'ledger_entry_id');
    }
}
