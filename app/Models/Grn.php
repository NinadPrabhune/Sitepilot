<?php

namespace App\Models;

use App\Traits\HasAssignTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grn extends Model
{
    use HasFactory, SoftDeletes, HasAssignTo;

    protected $fillable = [
        'grn_number',
        'grn_type',
        'po_id',
        'supplier_id',
        'site_id',
        'grn_date',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'delivery_challan_number',
        'vehicle_number',
        'gate_entry_number',
        'delivery_challan_file',
        'reference_file',
        'description',
        'received_by',
        'remarks',
        'status',
        'created_by',
        'workspace_id',
        'grn_pdf',
        'total_amount',
        'tax_type',
        'total_taxable_value',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total_tax',
        'is_locked',
        'assign_to',
    ];

    protected $casts = [
        'grn_date' => 'date',
        'supplier_invoice_date' => 'date',
        'total_amount' => 'decimal:2',
        'total_taxable_value' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'is_locked' => 'boolean',
    ];

    // Status constants
    const STATUS_PENDING = 'Pending';
    const STATUS_APPROVED = 'Approved';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_PARTIAL = 'Partial';

    // GRN type constants
    const TYPE_AGAINST_PO = 'against_po';
    const TYPE_DIRECT = 'direct';

    // Tax type constants
    const TAX_TYPE_CGST = 'cgst';
    const TAX_TYPE_IGST = 'igst';

    /**
     * Get the purchase order for this GRN.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    /**
     * Get the supplier for this GRN.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the site for this GRN.
     */
    public function site()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'site_id');
    }

    /**
     * Get the creator of the GRN.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the workspace for this GRN.
     */
    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class, 'workspace_id');
    }

    /**
     * Get the items for this GRN.
     */
    public function items()
    {
        return $this->hasMany(GrnItem::class);
    }

    /**
     * Check if this is a direct GRN.
     */
    public function isDirectGrn(): bool
    {
        return $this->grn_type === self::TYPE_DIRECT;
    }

    /**
     * Check if this is a PO-based GRN.
     */
    public function isPoBasedGrn(): bool
    {
        return $this->grn_type === self::TYPE_AGAINST_PO;
    }

    /**
     * Calculate totals from items (for direct GRN).
     */
    public function calculateTotals(): void
    {
        if (!$this->isDirectGrn()) {
            return;
        }

        $this->load('items.gstMaster');

        $totalTaxableValue = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($this->items as $item) {
            $quantity = (float) $item->received_qty;
            $price = (float) $item->price;

            $rowTotal = $quantity * $price;
            $taxableValue = $rowTotal;

            $totalTaxableValue += $taxableValue;

            $gstMaster = $item->gstMaster;

            if ($gstMaster) {
                if ($this->tax_type === self::TAX_TYPE_IGST) {
                    $igstRate = (float) ($gstMaster->igst ?? 0);
                    $igstAmount = ($taxableValue * $igstRate) / 100;
                    $totalIgst += $igstAmount;
                } else {
                    $cgstRate = (float) ($gstMaster->cgst ?? 0);
                    $sgstRate = (float) ($gstMaster->sgst ?? 0);

                    $cgstAmount = ($taxableValue * $cgstRate) / 100;
                    $sgstAmount = ($taxableValue * $sgstRate) / 100;

                    $totalCgst += $cgstAmount;
                    $totalSgst += $sgstAmount;
                }
            }
        }

        $totalTax = ($this->tax_type === self::TAX_TYPE_IGST)
            ? $totalIgst
            : ($totalCgst + $totalSgst);

        // Assign rounded values
        $this->total_taxable_value = round($totalTaxableValue, 2);
        $this->total_cgst = round($totalCgst, 2);
        $this->total_sgst = round($totalSgst, 2);
        $this->total_igst = round($totalIgst, 2);
        $this->total_tax = round($totalTax, 2);
        $this->total_amount = round($totalTaxableValue + $totalTax, 2);
    }

    /**
     * Generate unique GRN number with per-site reset.
     */
    public static function generateGrnNumber(?int $siteId = null): string
    {
        return app(\App\Services\NumberGeneratorService::class)->generate('grn', $siteId);
    }

    /**
     * Check if GRN is fully completed (all items received).
     */
    public function isCompleted()
    {
        return $this->items->every(function ($item) {
            return $item->accepted_qty + $item->rejected_qty == $item->ordered_qty;
        });
    }

    /**
     * Get total accepted quantity for this GRN.
     */
    public function getTotalAcceptedQtyAttribute()
    {
        return $this->items->sum('accepted_qty');
    }

    /**
     * Get total rejected quantity for this GRN.
     */
    public function getTotalRejectedQtyAttribute()
    {
        return $this->items->sum('rejected_qty');
    }

    /**
     * Get total received quantity for this GRN.
     */
    public function getTotalReceivedQtyAttribute()
    {
        return $this->items->sum('received_qty');
    }

    /**
     * Get the purchase invoice for this GRN.
     */
    public function purchaseInvoice()
    {
        return $this->hasOne(PurchaseInvoice::class, 'grn_id');
    }

    /**
     * Check if invoice exists for this GRN.
     */
    public function hasInvoice(): bool
    {
        return PurchaseInvoice::where('grn_id', $this->id)->exists();
    }

    /**
     * Get invoice if exists for this GRN.
     */
    public function getInvoice()
    {
        return PurchaseInvoice::where('grn_id', $this->id)->first();
    }
}
