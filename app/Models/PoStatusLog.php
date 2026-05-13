<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoStatusLog extends Model
{
    use HasFactory;

    protected $table = 'po_status_logs';

    protected $fillable = [
        'purchase_order_id',
        'old_status',
        'new_status',
        'reason',
        'changed_by',
    ];

    /**
     * Get the purchase order that owns this status log.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * Get the user who changed the status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Log a status change for a purchase order.
     */
    public static function logStatusChange(
        int $purchaseOrderId,
        string $oldStatus,
        string $newStatus,
        ?string $reason = null,
        ?int $changedBy = null
    ): self {
        return self::create([
            'purchase_order_id' => $purchaseOrderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $changedBy,
        ]);
    }
}
