# 🔄 PHASE 1: DIRECT GRN IMPLEMENTATION PLAN

## 📋 OVERVIEW

This document provides a detailed implementation plan for supporting **Direct GRN (without PO & Indent)** in the Procurement & Inventory Management System.

---

## 1. 🎯 OBJECTIVE

Enable creation of GRN (Goods Receipt Note) without requiring a Purchase Order, allowing flexibility for:
- Emergency purchases
- Small value purchases
- Cash purchases
- Direct supplier deliveries

---

## 2. 📊 CURRENT STATE ANALYSIS

### 2.1 Current GRN Structure

**Database Schema** (`grns` table):
```sql
- id (PK)
- grn_number (unique)
- po_id (FK to purchase_orders) -- REQUIRED, NOT NULL
- supplier_id (FK to suppliers)
- site_id (FK to projects)
- grn_date
- delivery_challan_number
- vehicle_number
- gate_entry_number
- delivery_challan_file
- reference_file
- description
- received_by
- remarks
- status (Pending/Completed/Partial)
- created_by
- workspace_id
- grn_pdf
- timestamps
- soft_deletes
```

**Current Limitation**:
- `po_id` is REQUIRED (NOT NULL constraint)
- Cannot create GRN without a PO
- Foreign key constraint: `po_id` references `purchase_orders.id` with CASCADE delete

### 2.2 Current GRN Items Structure

**Database Schema** (`grn_items` table):
```sql
- id (PK)
- grn_id (FK to grns)
- po_item_id (FK to purchase_order_items) -- REQUIRED
- material_id (FK to materials)
- ordered_qty
- received_qty
- accepted_qty
- rejected_qty
- remarks
```

**Current Limitation**:
- `po_item_id` is REQUIRED
- Cannot create GRN item without a PO item
- No price information stored in GRN items

---

## 3. 🛠️ REQUIRED CHANGES

### 3.1 Database Changes

#### A. Modify `grns` Table

**Migration File**: `database/migrations/2026_03_31_000002_modify_grns_for_direct_grn.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            // 1. Make po_id nullable
            $table->foreignId('po_id')
                ->nullable()
                ->change();
            
            // 2. Add grn_type field
            $table->enum('grn_type', ['against_po', 'direct'])
                ->default('against_po')
                ->after('grn_number');
            
            // 3. Add supplier invoice fields (for direct GRN)
            $table->string('supplier_invoice_number')
                ->nullable()
                ->after('grn_date');
            
            $table->date('supplier_invoice_date')
                ->nullable()
                ->after('supplier_invoice_number');
            
            // 4. Add financial fields (for direct GRN)
            $table->decimal('total_amount', 15, 2)
                ->default(0)
                ->after('supplier_invoice_date');
            
            $table->string('tax_type')
                ->nullable()
                ->after('total_amount');
            
            $table->decimal('total_taxable_value', 15, 2)
                ->default(0)
                ->after('tax_type');
            
            $table->decimal('total_cgst', 15, 2)
                ->default(0)
                ->after('total_taxable_value');
            
            $table->decimal('total_sgst', 15, 2)
                ->default(0)
                ->after('total_cgst');
            
            $table->decimal('total_igst', 15, 2)
                ->default(0)
                ->after('total_sgst');
            
            $table->decimal('total_tax', 15, 2)
                ->default(0)
                ->after('total_igst');
            
            // 5. Add indexes
            $table->index('grn_type');
            $table->index('supplier_invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex(['grn_type']);
            $table->dropIndex(['supplier_invoice_number']);
            
            // Remove financial fields
            $table->dropColumn([
                'total_tax',
                'total_igst',
                'total_sgst',
                'total_cgst',
                'total_taxable_value',
                'tax_type',
                'total_amount',
                'supplier_invoice_date',
                'supplier_invoice_number',
                'grn_type',
            ]);
            
            // Make po_id required again
            $table->foreignId('po_id')
                ->nullable(false)
                ->change();
        });
    }
};
```

#### B. Modify `grn_items` Table

**Migration File**: `database/migrations/2026_03_31_000003_modify_grn_items_for_direct_grn.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            // 1. Make po_item_id nullable
            $table->foreignId('po_item_id')
                ->nullable()
                ->change();
            
            // 2. Add price fields (for direct GRN)
            $table->decimal('price', 15, 2)
                ->default(0)
                ->after('rejected_qty');
            
            $table->decimal('tax_amount', 15, 2)
                ->default(0)
                ->after('price');
            
            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->after('tax_amount');
            
            // 3. Add GST master reference
            $table->unsignedBigInteger('gst_master_id')
                ->nullable()
                ->after('subtotal');
            
            $table->foreign('gst_master_id')
                ->references('id')
                ->on('gst_masters')
                ->onDelete('set null');
            
            // 4. Add index
            $table->index('gst_master_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            // Remove index
            $table->dropIndex(['gst_master_id']);
            
            // Remove foreign key
            $table->dropForeign(['gst_master_id']);
            
            // Remove fields
            $table->dropColumn([
                'gst_master_id',
                'subtotal',
                'tax_amount',
                'price',
            ]);
            
            // Make po_item_id required again
            $table->foreignId('po_item_id')
                ->nullable(false)
                ->change();
        });
    }
};
```

### 3.2 Model Changes

#### A. Update `Grn` Model

**File**: `app/Models/Grn.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grn extends Model
{
    use HasFactory, SoftDeletes;

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
    ];

    // Status constants
    const STATUS_PENDING = 'Pending';
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
     * Generate unique GRN number.
     */
    public static function generateGrnNumber()
    {
        $prefix = 'GRN-';
        $lastGrn = self::orderBy('id', 'desc')->first();
        
        if ($lastGrn) {
            $lastNumber = intval(substr($lastGrn->grn_number, 4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
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
```

#### B. Update `GrnItem` Model

**File**: `app/Models/GrnItem.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_id',
        'po_item_id',
        'material_id',
        'ordered_qty',
        'received_qty',
        'accepted_qty',
        'rejected_qty',
        'price',
        'tax_amount',
        'subtotal',
        'gst_master_id',
        'remarks',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:3',
        'received_qty' => 'decimal:3',
        'accepted_qty' => 'decimal:3',
        'rejected_qty' => 'decimal:3',
        'price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Get the GRN for this item.
     */
    public function grn()
    {
        return $this->belongsTo(Grn::class);
    }

    /**
     * Get the PO item for this GRN item.
     */
    public function poItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }

    /**
     * Get the material for this GRN item.
     */
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the GST master for this item.
     */
    public function gstMaster()
    {
        return $this->belongsTo(GstMaster::class, 'gst_master_id');
    }

    /**
     * Get remaining quantity for this PO item.
     */
    public function getRemainingQtyAttribute()
    {
        if (!$this->poItem) {
            return 0;
        }
        return $this->ordered_qty - ($this->poItem->received_qty ?? 0);
    }

    /**
     * Calculate and set rejected qty based on received and accepted.
     */
    public function setRejectedQtyAttribute($value)
    {
        // If received_qty and accepted_qty are set, calculate rejected
        if (isset($this->attributes['received_qty']) && isset($this->attributes['accepted_qty'])) {
            $this->attributes['rejected_qty'] = $this->attributes['received_qty'] - $this->attributes['accepted_qty'];
        } else {
            $this->attributes['rejected_qty'] = $value;
        }
    }

    /**
     * Calculate subtotal for direct GRN item.
     */
    public function calculateSubtotal(): void
    {
        if (!$this->grn || !$this->grn->isDirectGrn()) {
            return;
        }

        $quantity = (float) $this->received_qty;
        $price = (float) $this->price;

        $rowTotal = $quantity * $price;
        $taxableValue = $rowTotal;

        // Calculate tax based on parent tax_type
        $grn = $this->grn;
        $gstMaster = $this->gstMaster;

        $taxAmount = 0;
        if ($gstMaster && $grn) {
            if ($grn->tax_type === Grn::TAX_TYPE_IGST) {
                $taxAmount = $taxableValue * ($gstMaster->igst / 100);
            } else {
                $taxAmount = $taxableValue * (($gstMaster->cgst + $gstMaster->sgst) / 100);
            }
        }

        $this->tax_amount = $taxAmount;
        $this->subtotal = $taxableValue + $taxAmount;
    }

    /**
     * Boot method to auto-calculate subtotal before saving.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if ($item->grn && $item->grn->isDirectGrn()) {
                $item->calculateSubtotal();
            }
        });
    }
}
```

### 3.3 Service Layer Changes

#### A. Create `GrnService`

**File**: `app/Services/GrnService.php`

```php
<?php

namespace App\Services;

use App\Models\Grn;
use App\Models\GrnItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Helpers\LedgerHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GrnService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Create GRN against PO.
     */
    public function createGrnAgainstPo(array $data): Grn
    {
        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::findOrFail($data['po_id']);

            // Validate PO status
            if (!$po->canCreateGrn()) {
                throw new \Exception('PO cannot have GRN in current status');
            }

            // Create GRN
            $grn = Grn::create([
                'grn_number' => Grn::generateGrnNumber(),
                'grn_type' => Grn::TYPE_AGAINST_PO,
                'po_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'site_id' => $po->site_id,
                'grn_date' => $data['grn_date'],
                'delivery_challan_number' => $data['delivery_challan_number'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'gate_entry_number' => $data['gate_entry_number'] ?? null,
                'received_by' => $data['received_by'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => Grn::STATUS_PENDING,
                'created_by' => auth()->id(),
                'workspace_id' => getActiveWorkSpace(),
            ]);

            // Create GRN items
            foreach ($data['items'] as $item) {
                $poItem = $po->items()->findOrFail($item['po_item_id']);

                // Validate quantity
                $remainingQty = $poItem->quantity - ($poItem->received_qty ?? 0);
                if ($item['received_qty'] > $remainingQty) {
                    throw new \Exception("Received quantity exceeds remaining quantity for item {$poItem->material->name}");
                }

                $grn->items()->create([
                    'po_item_id' => $poItem->id,
                    'material_id' => $poItem->material_id,
                    'ordered_qty' => $poItem->quantity,
                    'received_qty' => $item['received_qty'],
                    'accepted_qty' => $item['accepted_qty'],
                    'rejected_qty' => $item['received_qty'] - $item['accepted_qty'],
                    'remarks' => $item['remarks'] ?? null,
                ]);

                // Update PO item received quantity
                $poItem->received_qty = ($poItem->received_qty ?? 0) + $item['accepted_qty'];
                $poItem->save();
            }

            // Update PO status
            $po->updateStatusFromGrn();

            // Update stock
            $this->stockService->addGrnStock($grn);

            Log::info('GRN created against PO', [
                'grn_id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'po_id' => $po->id,
                'po_number' => $po->po_number,
            ]);

            return $grn;
        });
    }

    /**
     * Create Direct GRN (without PO).
     */
    public function createDirectGrn(array $data): Grn
    {
        return DB::transaction(function () use ($data) {
            // Validate supplier invoice number uniqueness
            if (!empty($data['supplier_invoice_number'])) {
                $exists = Grn::where('supplier_id', $data['supplier_id'])
                    ->where('supplier_invoice_number', $data['supplier_invoice_number'])
                    ->exists();

                if ($exists) {
                    throw new \Exception('Supplier invoice number already exists for this supplier');
                }
            }

            // Create GRN
            $grn = Grn::create([
                'grn_number' => Grn::generateGrnNumber(),
                'grn_type' => Grn::TYPE_DIRECT,
                'po_id' => null,
                'supplier_id' => $data['supplier_id'],
                'site_id' => $data['site_id'],
                'grn_date' => $data['grn_date'],
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_invoice_date' => $data['supplier_invoice_date'] ?? null,
                'delivery_challan_number' => $data['delivery_challan_number'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'gate_entry_number' => $data['gate_entry_number'] ?? null,
                'received_by' => $data['received_by'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'tax_type' => $data['tax_type'] ?? Grn::TAX_TYPE_CGST,
                'status' => Grn::STATUS_PENDING,
                'created_by' => auth()->id(),
                'workspace_id' => getActiveWorkSpace(),
            ]);

            // Create GRN items
            foreach ($data['items'] as $item) {
                $grn->items()->create([
                    'po_item_id' => null,
                    'material_id' => $item['material_id'],
                    'ordered_qty' => $item['quantity'],
                    'received_qty' => $item['quantity'],
                    'accepted_qty' => $item['accepted_qty'],
                    'rejected_qty' => $item['quantity'] - $item['accepted_qty'],
                    'price' => $item['price'],
                    'gst_master_id' => $item['gst_master_id'] ?? null,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            // Calculate totals
            $grn->calculateTotals();
            $grn->save();

            // Update stock
            $this->stockService->addGrnStock($grn);

            // Create supplier ledger entry
            LedgerHelper::supplierLedger([
                'supplier_id' => $grn->supplier_id,
                'site_id' => $grn->site_id,
                'reference_type' => 'grn',
                'reference_id' => $grn->id,
                'transaction_date' => $grn->grn_date,
                'debit' => $grn->total_amount,
                'credit' => 0,
                'description' => "Direct GRN {$grn->grn_number}",
                'workspace_id' => $grn->workspace_id,
                'created_by' => $grn->created_by,
            ]);

            Log::info('Direct GRN created', [
                'grn_id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'supplier_id' => $grn->supplier_id,
                'total_amount' => $grn->total_amount,
            ]);

            return $grn;
        });
    }

    /**
     * Update GRN status.
     */
    public function updateStatus(Grn $grn): void
    {
        if ($grn->isCompleted()) {
            $grn->status = Grn::STATUS_COMPLETED;
        } else {
            $grn->status = Grn::STATUS_PARTIAL;
        }

        $grn->save();
    }

    /**
     * Validate GRN data.
     */
    public function validateGrn(array $data, ?int $grnId = null): array
    {
        $errors = [];

        // Common validations
        if (empty($data['supplier_id'])) {
            $errors['supplier_id'] = 'Supplier is required';
        }

        if (empty($data['site_id'])) {
            $errors['site_id'] = 'Site is required';
        }

        if (empty($data['grn_date'])) {
            $errors['grn_date'] = 'GRN date is required';
        }

        if (empty($data['items']) || count($data['items']) === 0) {
            $errors['items'] = 'At least one item is required';
        }

        // Type-specific validations
        if (isset($data['grn_type']) && $data['grn_type'] === Grn::TYPE_DIRECT) {
            // Direct GRN validations
            if (empty($data['supplier_invoice_number'])) {
                $errors['supplier_invoice_number'] = 'Supplier invoice number is required for direct GRN';
            }

            if (empty($data['tax_type'])) {
                $errors['tax_type'] = 'Tax type is required for direct GRN';
            }

            // Validate items
            foreach ($data['items'] as $index => $item) {
                if (empty($item['price'])) {
                    $errors["items.{$index}.price"] = 'Price is required for direct GRN';
                }

                if (empty($item['gst_master_id'])) {
                    $errors["items.{$index}.gst_master_id"] = 'GST master is required for direct GRN';
                }
            }
        } else {
            // PO-based GRN validations
            if (empty($data['po_id'])) {
                $errors['po_id'] = 'Purchase Order is required';
            }

            // Validate items
            foreach ($data['items'] as $index => $item) {
                if (empty($item['po_item_id'])) {
                    $errors["items.{$index}.po_item_id"] = 'PO item is required';
                }
            }
        }

        return $errors;
    }
}
```

### 3.4 Controller Changes

#### A. Update `GrnController`

**File**: `app/Http/Controllers/GrnController.php`

Add the following methods:

```php
/**
 * Show the form for creating a new GRN.
 */
public function create(Request $request)
{
    $workspaceId = getActiveWorkSpace();
    $siteId = getActiveProject();

    // Get all approved AND partial received purchase orders
    $purchaseOrders = PurchaseOrder::where('workspace_id', $workspaceId)
        ->where('site_id', $siteId)
        ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED])
        ->with(['supplier', 'site', 'items.material'])
        ->get()
        ->filter(function($po) {
            // Only show POs that have remaining quantities to receive
            foreach ($po->items as $item) {
                if (($item->quantity - ($item->received_qty ?? 0)) > 0) {
                    return true;
                }
            }
            return false;
        })
        ->values();

    // Get suppliers for direct GRN
    $suppliers = Supplier::orderBy('name')->get();

    // Get materials for direct GRN
    $materials = Material::with('category', 'unit', 'gstMaster')->get();

    // Get GST masters for direct GRN
    $gstMasters = GstMaster::where('is_active', true)->get();

    return view('grn.create', compact(
        'purchaseOrders',
        'suppliers',
        'materials',
        'gstMasters',
        'siteId'
    ));
}

/**
 * Store a newly created GRN.
 */
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'grn_type' => 'required|in:against_po,direct',
        'supplier_id' => 'required|exists:suppliers,id',
        'site_id' => 'required|exists:projects,id',
        'grn_date' => 'required|date',
        'po_id' => 'required_if:grn_type,against_po|exists:purchase_orders,id',
        'supplier_invoice_number' => 'required_if:grn_type,direct',
        'supplier_invoice_date' => 'nullable|date',
        'tax_type' => 'required_if:grn_type,direct|in:cgst,igst',
        'delivery_challan_number' => 'nullable|string',
        'vehicle_number' => 'nullable|string',
        'gate_entry_number' => 'nullable|string',
        'received_by' => 'nullable|string',
        'remarks' => 'nullable|string',
        'items' => 'required|array|min:1',
        'items.*.material_id' => 'required|exists:materials,id',
        'items.*.po_item_id' => 'required_if:grn_type,against_po|exists:purchase_order_items,id',
        'items.*.received_qty' => 'required|numeric|min:0.001',
        'items.*.accepted_qty' => 'required|numeric|min:0',
        'items.*.price' => 'required_if:grn_type,direct|numeric|min:0',
        'items.*.gst_master_id' => 'required_if:grn_type,direct|exists:gst_masters,id',
        'items.*.remarks' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    try {
        $grnService = new GrnService(new StockService());

        if ($request->grn_type === 'direct') {
            $grn = $grnService->createDirectGrn($request->all());
        } else {
            $grn = $grnService->createGrnAgainstPo($request->all());
        }

        return redirect()->route('grn.show', $grn->id)
            ->with('success', 'GRN created successfully');

    } catch (\Exception $e) {
        Log::error('GRN creation failed', [
            'error' => $e->getMessage(),
            'data' => $request->all(),
        ]);

        return back()->withErrors(['error' => $e->getMessage()])->withInput();
    }
}
```

#### B. Update `GrnApiController`

**File**: `app/Http/Controllers/Api/GrnApiController.php`

Add the following methods:

```php
/**
 * Store a newly created GRN.
 */
public function store(Request $request)
{
    if (!Auth::user()->isAbleTo('grn create')) {
        return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
    }

    try {
        $validator = Validator::make($request->all(), [
            'grn_type' => 'required|in:against_po,direct',
            'supplier_id' => 'required|exists:suppliers,id',
            'site_id' => 'required|exists:projects,id',
            'grn_date' => 'required|date',
            'po_id' => 'required_if:grn_type,against_po|exists:purchase_orders,id',
            'supplier_invoice_number' => 'required_if:grn_type,direct',
            'supplier_invoice_date' => 'nullable|date',
            'tax_type' => 'required_if:grn_type,direct|in:cgst,igst',
            'delivery_challan_number' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
            'gate_entry_number' => 'nullable|string',
            'received_by' => 'nullable|string',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.po_item_id' => 'required_if:grn_type,against_po|exists:purchase_order_items,id',
            'items.*.received_qty' => 'required|numeric|min:0.001',
            'items.*.accepted_qty' => 'required|numeric|min:0',
            'items.*.price' => 'required_if:grn_type,direct|numeric|min:0',
            'items.*.gst_master_id' => 'required_if:grn_type,direct|exists:gst_masters,id',
            'items.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $grnService = new GrnService(new StockService());

        if ($request->grn_type === 'direct') {
            $grn = $grnService->createDirectGrn($request->all());
        } else {
            $grn = $grnService->createGrnAgainstPo($request->all());
        }

        // Load relationships
        $grn->load(['supplier', 'site', 'items.material', 'items.gstMaster', 'purchaseOrder']);

        return response()->json([
            'status' => 1,
            'message' => 'GRN created successfully',
            'data' => $grn,
        ], 201);

    } catch (\Exception $e) {
        Log::error('GRN creation API error', [
            'error' => $e->getMessage(),
            'data' => $request->all(),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Failed to create GRN',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Get create data for GRN form.
 */
public function createData(Request $request)
{
    if (!Auth::user()->isAbleTo('grn create')) {
        return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
    }

    try {
        $workspaceId = $request->input('workspace_id');
        $siteId = $request->input('site_id');

        if (empty($workspaceId) && function_exists('getActiveWorkSpace')) {
            $workspaceId = getActiveWorkSpace();
        }

        if (empty($siteId) && function_exists('getActiveProject')) {
            $siteId = getActiveProject();
        }

        // Get POs for PO-based GRN
        $purchaseOrders = PurchaseOrder::where('workspace_id', $workspaceId)
            ->where('site_id', $siteId)
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED])
            ->with(['supplier', 'site', 'items.material'])
            ->get()
            ->filter(function($po) {
                foreach ($po->items as $item) {
                    if (($item->quantity - ($item->received_qty ?? 0)) > 0) {
                        return true;
                    }
                }
                return false;
            })
            ->values();

        // Get suppliers for direct GRN
        $suppliers = Supplier::orderBy('name')->get();

        // Get materials for direct GRN
        $materials = Material::with('category', 'unit', 'gstMaster')->get();

        // Get GST masters for direct GRN
        $gstMasters = GstMaster::where('is_active', true)->get();

        return response()->json([
            'status' => 1,
            'message' => 'Create data fetched successfully',
            'data' => [
                'purchase_orders' => $purchaseOrders,
                'suppliers' => $suppliers,
                'materials' => $materials,
                'gst_masters' => $gstMasters,
            ],
        ], 200);

    } catch (\Exception $e) {
        Log::error('GRN create data API error', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Failed to fetch create data',
            'error' => $e->getMessage(),
        ], 500);
    }
}
```

---

## 4. 🧪 TESTING PLAN

### 4.1 Unit Tests

**File**: `tests/Unit/GrnServiceTest.php`

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GrnService;
use App\Services\StockService;
use App\Models\Grn;
use App\Models\Supplier;
use App\Models\Material;
use App\Models\GstMaster;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GrnServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $grnService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grnService = new GrnService(new StockService());
    }

    /** @test */
    public function it_can_create_direct_grn()
    {
        $supplier = Supplier::factory()->create();
        $material = Material::factory()->create();
        $gstMaster = GstMaster::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'site_id' => 1,
            'grn_date' => now()->toDateString(),
            'supplier_invoice_number' => 'INV-001',
            'tax_type' => 'cgst',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 10,
                    'accepted_qty' => 10,
                    'price' => 100,
                    'gst_master_id' => $gstMaster->id,
                ],
            ],
        ];

        $grn = $this->grnService->createDirectGrn($data);

        $this->assertInstanceOf(Grn::class, $grn);
        $this->assertEquals('direct', $grn->grn_type);
        $this->assertNull($grn->po_id);
        $this->assertEquals('INV-001', $grn->supplier_invoice_number);
        $this->assertCount(1, $grn->items);
    }

    /** @test */
    public function it_validates_supplier_invoice_number_uniqueness()
    {
        $supplier = Supplier::factory()->create();
        $material = Material::factory()->create();
        $gstMaster = GstMaster::factory()->create();

        // Create first GRN
        $this->grnService->createDirectGrn([
            'supplier_id' => $supplier->id,
            'site_id' => 1,
            'grn_date' => now()->toDateString(),
            'supplier_invoice_number' => 'INV-001',
            'tax_type' => 'cgst',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 10,
                    'accepted_qty' => 10,
                    'price' => 100,
                    'gst_master_id' => $gstMaster->id,
                ],
            ],
        ]);

        // Try to create another GRN with same invoice number
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Supplier invoice number already exists');

        $this->grnService->createDirectGrn([
            'supplier_id' => $supplier->id,
            'site_id' => 1,
            'grn_date' => now()->toDateString(),
            'supplier_invoice_number' => 'INV-001',
            'tax_type' => 'cgst',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 5,
                    'accepted_qty' => 5,
                    'price' => 100,
                    'gst_master_id' => $gstMaster->id,
                ],
            ],
        ]);
    }

    /** @test */
    public function it_calculates_totals_correctly_for_direct_grn()
    {
        $supplier = Supplier::factory()->create();
        $material = Material::factory()->create();
        $gstMaster = GstMaster::factory()->create([
            'cgst' => 9,
            'sgst' => 9,
            'igst' => 18,
        ]);

        $data = [
            'supplier_id' => $supplier->id,
            'site_id' => 1,
            'grn_date' => now()->toDateString(),
            'supplier_invoice_number' => 'INV-001',
            'tax_type' => 'cgst',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 10,
                    'accepted_qty' => 10,
                    'price' => 100,
                    'gst_master_id' => $gstMaster->id,
                ],
            ],
        ];

        $grn = $this->grnService->createDirectGrn($data);

        $this->assertEquals(1000, $grn->total_taxable_value); // 10 * 100
        $this->assertEquals(90, $grn->total_cgst); // 1000 * 9%
        $this->assertEquals(90, $grn->total_sgst); // 1000 * 9%
        $this->assertEquals(180, $grn->total_tax); // 90 + 90
        $this->assertEquals(1180, $grn->total_amount); // 1000 + 180
    }
}
```

### 4.2 Feature Tests

**File**: `tests/Feature/GrnApiTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Material;
use App\Models\GstMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GrnApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_direct_grn_via_api()
    {
        $supplier = Supplier::factory()->create();
        $material = Material::factory()->create();
        $gstMaster = GstMaster::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/grn', [
                'grn_type' => 'direct',
                'supplier_id' => $supplier->id,
                'site_id' => 1,
                'grn_date' => now()->toDateString(),
                'supplier_invoice_number' => 'INV-001',
                'tax_type' => 'cgst',
                'items' => [
                    [
                        'material_id' => $material->id,
                        'quantity' => 10,
                        'accepted_qty' => 10,
                        'price' => 100,
                        'gst_master_id' => $gstMaster->id,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'grn_number',
                    'grn_type',
                    'supplier_invoice_number',
                    'total_amount',
                ],
            ]);
    }

    /** @test */
    public function it_validates_required_fields_for_direct_grn()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/grn', [
                'grn_type' => 'direct',
                'supplier_id' => 1,
                'site_id' => 1,
                'grn_date' => now()->toDateString(),
                // Missing supplier_invoice_number
                'tax_type' => 'cgst',
                'items' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_invoice_number', 'items']);
    }
}
```

---

## 5. 📋 IMPLEMENTATION CHECKLIST

### 5.1 Database Changes

- [ ] Create migration for `grns` table modifications
- [ ] Create migration for `grn_items` table modifications
- [ ] Run migrations
- [ ] Verify database schema

### 5.2 Model Changes

- [ ] Update `Grn` model with new fields and methods
- [ ] Update `GrnItem` model with new fields and methods
- [ ] Add relationships and accessors
- [ ] Add validation methods

### 5.3 Service Layer

- [ ] Create `GrnService` class
- [ ] Implement `createGrnAgainstPo()` method
- [ ] Implement `createDirectGrn()` method
- [ ] Implement `validateGrn()` method
- [ ] Add error handling and logging

### 5.4 Controller Changes

- [ ] Update `GrnController::create()` method
- [ ] Update `GrnController::store()` method
- [ ] Update `GrnApiController::store()` method
- [ ] Update `GrnApiController::createData()` method
- [ ] Add validation rules

### 5.5 Testing

- [ ] Create unit tests for `GrnService`
- [ ] Create feature tests for API endpoints
- [ ] Test PO-based GRN workflow
- [ ] Test Direct GRN workflow
- [ ] Test validation rules
- [ ] Test error handling

### 5.6 Documentation

- [ ] Update API documentation
- [ ] Create user guide for Direct GRN
- [ ] Document validation rules
- [ ] Document business logic

---

## 6. 🚀 DEPLOYMENT PLAN

### 6.1 Pre-deployment

1. **Backup Database**
   ```bash
   php artisan db:backup
   ```

2. **Run Tests**
   ```bash
   php artisan test
   ```

3. **Check for Breaking Changes**
   - Review all model changes
   - Review all controller changes
   - Review all service changes

### 6.2 Deployment Steps

1. **Deploy Code**
   ```bash
   git pull origin main
   composer install --no-dev
   ```

2. **Run Migrations**
   ```bash
   php artisan migrate
   ```

3. **Clear Cache**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **Restart Queue** (if using)
   ```bash
   php artisan queue:restart
   ```

### 6.3 Post-deployment

1. **Verify Functionality**
   - Test PO-based GRN creation
   - Test Direct GRN creation
   - Test stock updates
   - Test supplier ledger entries

2. **Monitor Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Check for Errors**
   - Review error logs
   - Check database for data integrity
   - Verify stock calculations

---

## 7. 📊 SUCCESS METRICS

### 7.1 Functional Metrics

- [ ] Direct GRN can be created without PO
- [ ] Stock is updated correctly for Direct GRN
- [ ] Supplier ledger entry is created for Direct GRN
- [ ] Invoice can be created from Direct GRN
- [ ] All validations work correctly

### 7.2 Performance Metrics

- [ ] GRN creation time < 2 seconds
- [ ] API response time < 500ms
- [ ] No database query N+1 issues

### 7.3 Quality Metrics

- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] Code coverage > 80%
- [ ] No critical security vulnerabilities

---

## 8. 🎯 CONCLUSION

This implementation plan provides a comprehensive approach to adding Direct GRN support to the Procurement & Inventory Management System. By following this plan, the system will gain:

1. **Flexibility**: Support for emergency and small value purchases
2. **Control**: Proper validation and approval workflows
3. **Visibility**: Complete tracking of all GRN types
4. **Integration**: Seamless integration with existing stock and financial systems

The implementation should be completed in **1-2 weeks** with proper testing and documentation.

---

**Document Version**: 1.0  
**Last Updated**: 2026-03-31  
**Author**: System Architect  
**Status**: Ready for Implementation
