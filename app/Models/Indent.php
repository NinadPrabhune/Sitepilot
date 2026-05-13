<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\SoftDeletes;

class Indent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'indent_number',
        'indent_date',
        'supplier_invoice_number',
        'supplier_id',
        'total_amount',
        'status',
        'site_id',
        'created_by',
        'workspace_id',
        'description',
        'rejection_reason',
        // New fields added
        'assign_to',
        'delivery_date',
        'remark',
        'reference_file',
    ];

    protected $casts = [
        'indent_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    // Status constants
    const STATUS_OPEN = 'Open';
    const STATUS_PARTIALLY_CLOSED = 'Partially Closed';
    const STATUS_CLOSED = 'Closed';

    /**
     * Get the total amount - calculates from items if not set or zero
     */
    public function getTotalAmountAttribute(): float
    {
        // If total_amount is already set, use it (even if 0)
        if (array_key_exists('total_amount', $this->attributes)) {
            return (float) $this->attributes['total_amount'];
        }
        
        // Otherwise calculate from items
        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum('subtotal');
        }
        
        // Fallback: load items and calculate
        return (float) $this->items()->sum('subtotal');
    }

    public function items()
    {
        return $this->hasMany(IndentItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function site()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'site_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'indent_id');
    }

    /**
     * Calculate total quantity ordered for a specific material from all purchase orders
     * Counts all non-rejected POs (Draft, Approved, Partial Received, Completed)
     */
    public function getOrderedQuantityForMaterial(int $materialId): float
    {
        // Count Draft, Approved, Partial Received, and Completed POs as consumed
        $validStatuses = [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIAL_RECEIVED,
            PurchaseOrder::STATUS_COMPLETED
        ];
        
        $totalOrdered = $this->purchaseOrders()
            ->whereIn('status', $validStatuses)
            ->whereNull('deleted_at')
            ->with(['items' => function($query) use ($materialId) {
                $query->where('material_id', $materialId);
            }])
            ->get()
            ->sum(function($po) {
                return $po->items->sum('quantity');
            });
        
        return $totalOrdered;
    }

    /**
     * Get ordered quantity for a specific material, optionally excluding a current PO
     * Used when editing an existing PO
     *
     * @param int $materialId Material ID
     * @param int|null $excludePoId Purchase Order ID to exclude (for edit mode)
     * @return float Total ordered quantity
     */
    public function getOrderedQuantityForMaterialWithExclusion(
        int $materialId, 
        ?int $excludePoId = null
    ): float {

        // Count Draft, Approved, Partial Received, and Completed POs as consumed
        $validStatuses = [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIAL_RECEIVED,
            PurchaseOrder::STATUS_COMPLETED
        ];

        return \DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.indent_id', $this->id)
            ->whereIn('purchase_orders.status', $validStatuses)
            ->when($excludePoId, function ($query) use ($excludePoId) {
                $query->where('purchase_orders.id', '!=', $excludePoId);
            })
            ->where('purchase_order_items.material_id', $materialId)
            ->sum('purchase_order_items.quantity');
    }

    /**
     * Get available quantity for editing a specific material in a PO
     * This is the main method for edit page
     *
     * Business Logic:
     * - When creating new PO: available = indent_qty - sum(all other pending/approved PO qty)
     * - When editing existing PO: available = indent_qty - sum(all other PO qty) + current_po_saved_qty
     *
     * @param int $materialId Material ID
     * @param int|null $currentPoId Current PO ID being edited (null for new PO)
     * @param float|null $currentPoItemQty Current saved quantity in the PO being edited
     * @return float Available quantity (never negative)
     */
    public function getAvailableQuantityForEdit(int $materialId, ?int $currentPoId = null): float
    {
        $indentItem = $this->items->where('material_id', $materialId)->first();

        if (!$indentItem) {
            return 0;
        }

        $indentQuantity = (float) $indentItem->quantity;

        // Already excludes current PO
        $orderedQuantity = $this->getOrderedQuantityForMaterialWithExclusion(
            $materialId,
            $currentPoId
        );

        // Available = indent_qty - other_po_qty
        $availableQty = $indentQuantity - $orderedQuantity;

        return max(0, $availableQty);
    }

    /**
     * Get detailed quantity information for a material (for API response)
     *
     * @param int $materialId Material ID
     * @param int|null $currentPoId Current PO ID being edited
     * @return array Detailed quantity breakdown
     */
    public function getQuantityDetailsForMaterial(int $materialId, ?int $currentPoId = null): array
    {
        $indentItem = $this->items->where('material_id', $materialId)->first();
        
        if (!$indentItem) {
            return [
                'material_id' => $materialId,
                'indent_quantity' => 0,
                'consumed_quantity' => 0,
                'remaining_quantity' => 0,
                'available_for_edit' => 0,
                'current_po_quantity' => 0,
            ];
        }
        
        $indentQuantity = (float) $indentItem->quantity;
        
        // Get consumed from OTHER POs only (excluding current PO)
        $consumedQuantity = $this->getOrderedQuantityForMaterialWithExclusion($materialId, $currentPoId);
        
        // Remaining = indent - consumed by others
        $remainingQuantity = max(0, $indentQuantity - $consumedQuantity);
        
        // Get current PO quantity if editing
        $currentPoQuantity = 0;
        if ($currentPoId) {
            $currentPo = PurchaseOrder::find($currentPoId);
            if ($currentPo && $currentPo->indent_id == $this->id) {
                $currentPoItem = $currentPo->items->where('material_id', $materialId)->first();
                $currentPoQuantity = $currentPoItem ? (float) $currentPoItem->quantity : 0;
            }
        }
        
        // For EDIT mode: available = indent_qty - sum(other PO qty) + current_po_qty
        // This gives the actual available quantity including what this PO already has
        // For CREATE mode: available = remainingQuantity (no current PO)
        $availableForEdit = ($currentPoId !== null) 
            ? ($remainingQuantity + $currentPoQuantity) 
            : $remainingQuantity;
        
        return [
            'material_id' => $materialId,
            'indent_quantity' => $indentQuantity,
            'consumed_quantity' => $consumedQuantity,
            'remaining_quantity' => $remainingQuantity,
            'available_for_edit' => $availableForEdit,
            'current_po_quantity' => $currentPoQuantity,
        ];
    }

    /**
     * Get all materials with availability information for a specific PO
     * Used for edit page loading
     * 
     * For create (poId = null): filters out items with remaining_quantity <= 0
     * For edit (poId = provided): returns all items including those with 0 remaining
     *
     * @param int|null $poId Purchase Order ID (null for new PO)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getItemsWithAvailability(?int $poId = null)
    {
        $items = $this->items->map(function($item) use ($poId) {
            $details = $this->getQuantityDetailsForMaterial($item->material_id, $poId);
            
            return [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material_name' => $item->material ? $item->material->name : 'Unknown',
                'quantity' => $item->quantity,  // indent quantity from indent_items table
                'indent_quantity' => $details['indent_quantity'],
                'unit' => $item->unit,
                'price' => $item->price,
                'remaining_quantity' => $details['remaining_quantity'],
                'available_for_edit' => $details['available_for_edit'],
                'consumed_quantity' => $details['consumed_quantity'],
            ];
        });
        
        // For create (poId = null), filter out materials with no remaining quantity
        if ($poId === null) {
            $items = $items->filter(function($item) {
                return $item['remaining_quantity'] > 0;
            })->values();
        }
        
        return $items;
    }

    /**
     * Calculate total quantity ordered for all materials
     * Counts all non-rejected POs (Draft, Approved, Partial Received, Completed)
     */
    public function getTotalOrderedQuantity(): float
    {
        $totalOrdered = 0;
        
        // Only count non-rejected POs
        $validStatuses = [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIAL_RECEIVED,
            PurchaseOrder::STATUS_COMPLETED
        ];
        
        foreach ($this->purchaseOrders as $purchaseOrder) {
            if (in_array($purchaseOrder->status, $validStatuses)) {
                foreach ($purchaseOrder->items as $item) {
                    $totalOrdered += $item->quantity;
                }
            }
        }
        
        return $totalOrdered;
    }

    /**
     * Get remaining quantity for a specific material
     * Only counts Pending and Approved POs
     */
    public function getRemainingQuantityForMaterial(int $materialId): float
    {
        $indentItem = $this->items()->where('material_id', $materialId)->first();
        
        if (!$indentItem) {
            return 0;
        }
        
        $indentQty = (float) $indentItem->quantity;

        // Sum quantity from all valid POs for this indent and material
        // Includes Draft, Approved, Partial Received, and Completed POs
        $usedQty = \App\Models\PurchaseOrderItem::whereHas('purchaseOrder', function($q) {
                $q->where('indent_id', $this->id)
                  ->whereIn('status', ['Draft', 'Approved', 'Partial Received', 'Completed']);
            })
            ->where('material_id', $materialId)
            ->sum('quantity');

        $remaining = $indentQty - $usedQty;

        return max(0, $remaining);
    }

    /**
     * Update indent status based on purchase order quantities
     */
    public function updateStatus(): void
    {
        $totalIndentQuantity = $this->items->sum('quantity');
        $totalOrderedQuantity = $this->getTotalOrderedQuantity();

        if ($totalOrderedQuantity >= $totalIndentQuantity) {
            $this->status = self::STATUS_CLOSED;
        } elseif ($totalOrderedQuantity > 0) {
            $this->status = self::STATUS_PARTIALLY_CLOSED;
        } else {
            $this->status = self::STATUS_OPEN;
        }

        $this->save();
    }

    /**
     * Check if indent is fully closed
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Check if indent can accept new purchase orders
     */
    public function canAcceptPurchaseOrder(): bool
    {
        return $this->status !== self::STATUS_CLOSED;
    }

    /**
     * Generate unique indent number with per-site reset.
     */
    public static function generateIndentNumber(?int $siteId = null): string
    {
        return app(\App\Services\NumberGeneratorService::class)->generate('indent', $siteId);
    }

   
}
