<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineryBillingItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'machinery_id',
        'supplier_id',
        'bill_id',
        'workspace_id',
        'from_date',
        'to_date',
        'total_hours',
        'total_diesel',
        'diesel_cost_actual',
        'diesel_cost_deducted',
        'diesel_responsibility',
        'amount',
        'rate_per_hour',
        'diesel_rate',
        'status',
        'remarks',
    ];

    protected $casts = [
        'total_hours' => 'decimal:2',
        'total_diesel' => 'decimal:2',
        'diesel_cost_actual' => 'decimal:2',
        'diesel_cost_deducted' => 'decimal:2',
        'amount' => 'decimal:2',
        'rate_per_hour' => 'decimal:2',
        'diesel_rate' => 'decimal:2',
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    protected $dates = [
        'from_date',
        'to_date',
    ];

    public function machinery()
    {
        return $this->belongsTo(Machinery::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bill()
    {
        return $this->belongsTo(MachineryBill::class);
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    /**
     * Scope to get items by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get unbilled items
     */
    public function scopeUnbilled($query)
    {
        return $query->whereNull('bill_id');
    }

    /**
     * Scope to get billed items
     */
    public function scopeBilled($query)
    {
        return $query->whereNotNull('bill_id');
    }

    /**
     * Check if item exists for machinery and date range
     */
    public static function existsForMachinery(int $machineryId, string $fromDate, string $toDate, int $workspaceId): bool
    {
        return self::where('machinery_id', $machineryId)
            ->where('from_date', $fromDate)
            ->where('to_date', $toDate)
            ->where('workspace_id', $workspaceId)
            ->exists();
    }
}
