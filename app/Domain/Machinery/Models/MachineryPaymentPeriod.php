<?php

namespace App\Domain\Machinery\Models;

use Illuminate\Database\Eloquent\Model;

class MachineryPaymentPeriod extends Model
{
    protected $fillable = [
        'machinery_id',
        'workspace_id',
        'start_date',
        'end_date',
        'is_locked',
        'locked_at',
        'payment_request_id',
        'notes',
        'created_by',
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];
    
    public function machinery()
    {
        return $this->belongsTo(\App\Models\Machinery::class);
    }
    
    public function lockedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'locked_by');
    }

    /**
     * Check if a given date for a machinery is within a locked period
     */
    public static function isDateLocked(int $machineryId, string $date): bool
    {
        return self::where('machinery_id', $machineryId)
            ->where('is_locked', true)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }
    
    public function paymentRequest()
    {
        return $this->belongsTo(MachineryPaymentRequest::class);
    }
    
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }
    
    public function scopeForDate($query, string $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }
}
