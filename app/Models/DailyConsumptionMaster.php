<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\Taskly\Entities\Project;

use App\Models\WorkSpace;

use App\Models\Machinery;

class DailyConsumptionMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumption_number',
        'consumption_date',
        'consumption_type',
        'machinery_id',
        'machinery_type',
        'site_id',
        'activity_completed_id',
        'daily_progress_report_id',
        'consumption_file',
        'ledger_entry_id',
        'supplier_ledger_entry_id',
        'workspace_id',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($consumption) {
            // Auto-generate consumption number if not provided
            if (empty($consumption->consumption_number)) {
                $consumption->consumption_number = self::generateConsumptionNumber($consumption);
            }
            
            // Hard link validation: Diesel/Fuel consumption cannot exist without machinery
            // Only require machinery for fuel consumption, not for general material consumption
            if (in_array($consumption->consumption_type ?? 'all', ['fuel'])) {
                if (!$consumption->machinery_id) {
                    throw new \RuntimeException('Diesel consumption cannot be created without machinery. Machinery ID is required.');
                }
                
                // Validate machinery exists
                $machinery = \App\Models\Machinery::find($consumption->machinery_id);
                if (!$machinery) {
                    throw new \RuntimeException('Invalid machinery ID. Machinery does not exist.');
                }
            }
        });

        static::updating(function ($consumption) {
            // Allow approval workflow to set ledger_entry_id
            $isSettingLedgerEntry = in_array('ledger_entry_id', array_keys($consumption->getDirty())) && 
                                   $consumption->getOriginal('ledger_entry_id') === null;
            
            // Allow file updates even when ledger entry exists (files don't affect financial integrity)
            $dirtyFields = $consumption->getDirty();
            $isFileUpdateOnly = count($dirtyFields) === 1 && isset($dirtyFields['consumption_file']);
            
            // Block edits if ledger entry already exists AND this is not the initial approval AND not just a file update
            if ($consumption->getOriginal('ledger_entry_id') && !$isSettingLedgerEntry && !$isFileUpdateOnly) {
                throw new \RuntimeException('Cannot edit consumption after ledger entry has been created. Use reversal instead.');
            }
        });
    }

    public $timestamps = true;

    public function details()
    {
        return $this->hasMany(DailyConsumptionDetails::class, 'daily_consumption_master_id');
    }

    /**
     * Alias for details() - DailyConsumptionDetails records
     */
    public function dailyConsumptionDetails()
    {
        return $this->hasMany(DailyConsumptionDetails::class, 'daily_consumption_master_id');
    }

    public function site()
    {
        return $this->belongsTo(Project::class, 'site_id');
    }

    public function ledgerEntry()
    {
        return $this->belongsTo(\App\Domain\Machinery\Models\MachineryLedger::class, 'ledger_entry_id');
    }

    public function supplierLedgerEntry()
    {
        return $this->belongsTo(\App\Models\SupplierLedger::class, 'supplier_ledger_entry_id');
    }

    public function machinery()
    {
        return $this->belongsTo(Machinery::class, 'machinery_id');
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function report()
    {
        return $this->belongsTo(DailyProgressReport::class, 'daily_progress_report_id');
    }

    /**
     * Get the activity completed that owns this consumption record.
     */
    public function activityCompleted()
    {
        return $this->belongsTo(ActivityCompleted::class, 'activity_completed_id');
    }

    /**
     * Generate unique consumption number
     */
    private static function generateConsumptionNumber($consumption): string
    {
        $prefix = 'CON';
        $siteId = $consumption->site_id ?? 1;
        
        // Get the last consumption number for this site
        $lastConsumption = self::where('site_id', $siteId)
            ->whereNotNull('consumption_number')
            ->orderBy('consumption_number', 'desc')
            ->first();
        
        $lastNumber = 0;
        if ($lastConsumption) {
            // Extract number from consumption number format
            preg_match('/(\d+)$/', $lastConsumption->consumption_number, $matches);
            $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;
        }
        
        $nextNumber = $lastNumber + 1;
        return $prefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

}
