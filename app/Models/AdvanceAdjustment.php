<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvanceAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_id',
        'purchase_invoice_id',
        'utilized_amount',
    ];

    protected $casts = [
        'utilized_amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(PaymentsModule::class, 'payment_id');
    }

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public static function getUtilizedAmount(int $paymentId): float
    {
        return self::withoutTrashed()
            ->where('payment_id', $paymentId)
            ->sum('utilized_amount');
    }

    public static function getAvailableAdvance(int $paymentId): float
    {
        $payment = PaymentsModule::find($paymentId);
        if (!$payment) {
            return 0;
        }

        $utilized = self::getUtilizedAmount($paymentId);
        return max(0, $payment->amount - $utilized);
    }

    public static function getUtilizedAmountWithoutTrashed(int $paymentId): float
    {
        return self::withoutTrashed()
            ->where('payment_id', $paymentId)
            ->sum('utilized_amount');
    }

    public static function sumForInvoiceWithoutTrashed(int $invoiceId): float
    {
        return self::withoutTrashed()
            ->where('purchase_invoice_id', $invoiceId)
            ->sum('utilized_amount');
    }

    public static function sumForPOWithoutTrashed(array $invoiceIds): float
    {
        return self::withoutTrashed()
            ->whereIn('purchase_invoice_id', $invoiceIds)
            ->sum('utilized_amount');
    }

    public function restore(): bool
    {
        return parent::restore();
    }
}