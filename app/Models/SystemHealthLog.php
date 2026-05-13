<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemHealthLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'orphan_count',
        'drift_count',
        'critical_count',
        'warning_count',
        'health_status',
        'block_operations',
        'total_payment_requests',
        'verified_payment_requests',
        'mismatch_payment_requests',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'block_operations' => 'boolean',
    ];

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }
}
