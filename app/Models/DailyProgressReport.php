<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;
use App\Models\WorkSpace;

class DailyProgressReport extends Model {

    use HasFactory, SoftDeletes;

    // Source type constants
    const SOURCE_TYPE_ACTIVITY = 'activity';
    const SOURCE_TYPE_MACHINERY_DIRECT = 'machinery_direct';
    const SOURCE_TYPE_IMPORTED = 'imported';
    const SOURCE_TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $fillable = [
        // Basic fields
        'date',
        'machinery_id',
        'machine_start_reading',
        'machine_end_reading',
        'machine_idle_reading',
        'number_of_operators',
        'operator_names',
        'work_details',
        'diesel_consumption',
        'maintenance_notes',
        'machinery_advances',
        'status',
        'created_by',
        'workspace_id',
        'site_id',
        'activity_completed_id',
        'source_type',
        // Override fields
        'override_rate',
        'override_reason',
        'override_by',
        'override_at',
        // Approval/Verification fields
        'approved_by',
        'approved_at',
        'verified_by',
        'verified_at',
        // Rejection fields
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        // Lock fields
        'is_locked',
        'locked_at',
        'locked_by',
        // Billing/Payment fields
        'billable_hours',
        'calculated_amount',
        'payment_status',
        'is_billed',
        'payment_request_id',
        'paid_at',
        'paid_by',
        'billed_at',
        // Ledger linkage
        'ledger_entry_id',
        'supplier_ledger_entry_id',
        // Lifecycle & Workflow
        'lifecycle_state',
        // Rate snapshot & Calculation
        'rate_snapshot',
        'calculation_hash',
        // Manual balance verification
        'manual_balance_check',
        'manual_balance_matched',
        'manual_balance_notes',
        // Anomaly tracking
        'orphan_count',
        'critical_drift_count',
        'hash_mismatch_count',
        // Reconciliation
        'total_entries',
        'total_reversals',
        'reversal_rate_percent',
        // System health
        'system_health_status',
        // Warning overrides
        'warning_override_count',
        'warning_overrides',
        // Capture tracking
        'captured_at',
        'captured_by',
        // Snapshot
        'snapshot_date',
        // Audit
        'audit_log',
        'deleted_by',
        // Pending approvals
        'pending_approvals',
        'oldest_pending_age_hours',
    ];

    protected $casts = [
        'date' => 'date',
        'is_locked' => 'boolean',
        'is_billed' => 'boolean',
        'manual_balance_check' => 'boolean',
        'manual_balance_matched' => 'boolean',
        'rate_snapshot' => 'array',
        'warning_overrides' => 'array',
        'pending_approvals' => 'array',
        'audit_log' => 'array',
    ];

    protected $dates = [
        'date',
        'approved_at',
        'verified_at',
        'rejected_at',
        'locked_at',
        'paid_at',
        'billed_at',
        'override_at',
        'captured_at',
        'snapshot_date',
        'created_at',
        'updated_at',
    ];

    /**
     * Boot the model to add event listeners
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($dpr) {
            // Hard link validation: DPR cannot exist without machinery
            if (!$dpr->machinery_id) {
                throw new \RuntimeException('DPR cannot be created without machinery. Machinery ID is required.');
            }
            if (!$dpr->site_id) {
                throw new \RuntimeException('Site is required for DPR creation.');
            }
            
            // Validate machinery exists
            $machinery = Machinery::find($dpr->machinery_id);
            if (!$machinery) {
                throw new \RuntimeException('Invalid machinery ID. Machinery does not exist.');
            }
            
            // Auto-set source_type based on activity_completed_id presence
            if (!$dpr->source_type) {
                $dpr->source_type = $dpr->activity_completed_id 
                    ? self::SOURCE_TYPE_ACTIVITY 
                    : self::SOURCE_TYPE_MACHINERY_DIRECT;
            }
            
            // Integrity validation: source_type must match activity_completed_id
            if ($dpr->source_type === self::SOURCE_TYPE_ACTIVITY && !$dpr->activity_completed_id) {
                throw new \RuntimeException('Activity flow requires activity_completed_id');
            }
            if ($dpr->source_type === self::SOURCE_TYPE_MACHINERY_DIRECT && $dpr->activity_completed_id) {
                throw new \RuntimeException('Direct machinery flow cannot have activity_completed_id');
            }
        });

        static::updating(function ($dpr) {
            // Allow approval workflow to set ledger_entry_id
            $isSettingLedgerEntry = in_array('ledger_entry_id', array_keys($dpr->getDirty())) && 
                                   $dpr->getOriginal('ledger_entry_id') === null;
            
            // Allow status updates (pending -> approved/rejected)
            $isStatusUpdate = in_array('status', array_keys($dpr->getDirty()));
            
            // Block edits if DPR is approved (regardless of ledger entry)
            if ($dpr->getOriginal('status') === 'approved' && !$isStatusUpdate) {
                throw new \RuntimeException('Cannot edit DPR after it has been approved. Use reversal instead.');
            }
            
            // Allow edits when ledger entry exists if ledger correction system is available
            if ($dpr->getOriginal('ledger_entry_id') && !$isSettingLedgerEntry) {
                if (class_exists('App\Domain\Machinery\Services\LedgerCorrectionService')) {
                    Log::info('DPR edit allowed with ledger correction system', [
                        'dpr_id' => $dpr->id,
                        'ledger_entry_id' => $dpr->getOriginal('ledger_entry_id')
                    ]);
                } else {
                    throw new \RuntimeException('Cannot edit DPR after ledger entry has been created. Use reversal instead.');
                }
            }
        });

        // Prevent soft delete if settled ledger entries exist
        static::deleting(function ($dpr) {
            $hasSettled = \App\Domain\Machinery\Models\MachineryLedger::where([
                'reference_type' => 'DailyProgressReport',
                'reference_id' => $dpr->id,
            ])->where('is_settled', true)->exists();
            
            if ($hasSettled) {
                throw new \RuntimeException('Cannot delete DPR with settled ledger entries');
            }
        });
    }

    // ✅ Relationships
    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workspace() {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    public function site() {
        return $this->belongsTo(Project::class, 'site_id');
    }

    // ✅ Accessor: Machine Hours
    public function getMachineHoursAttribute() {
        if ($this->machine_start_reading && $this->machine_end_reading) {
            return $this->machine_end_reading - $this->machine_start_reading;
        }
        return null;
    }

    public function machinery() {
        return $this->belongsTo(Machinery::class, 'machinery_id');
    }

    public function ledgerEntry()
    {
        return $this->belongsTo(\App\Domain\Machinery\Models\MachineryLedger::class, 'ledger_entry_id');
    }

    /**
     * Get the activity completed that owns this progress report.
     */
    public function activityCompleted()
    {
        return $this->belongsTo(ActivityCompleted::class, 'activity_completed_id');
    }
    
    public function consumptionMaster()
    {
        return $this->hasOne(DailyConsumptionMaster::class, 'daily_progress_report_id');
    }

    public function items()
    {
        return $this->hasManyThrough(
            DailyConsumptionDetails::class,
            DailyConsumptionMaster::class,
            'daily_progress_report_id',    // FK on DailyConsumptionMaster
            'daily_consumption_master_id', // FK on DailyConsumptionDetails
            'id',                          // Local key on DailyProgressReport
            'id'                           // Local key on DailyConsumptionMaster
        );
    }

    // New helper methods for dual flow
    public function isActivityFlow(): bool
    {
        return $this->source_type === self::SOURCE_TYPE_ACTIVITY;
    }

    public function isDirectMachineryFlow(): bool
    {
        return $this->source_type === self::SOURCE_TYPE_MACHINERY_DIRECT;
    }

    /**
     * Get ledger entries linked to this DPR
     */
    public function ledgerEntries()
    {
        return $this->hasMany(\App\Domain\Machinery\Models\MachineryLedger::class, 'reference_id')
            ->where('reference_type', 'DailyProgressReport');
    }
}
