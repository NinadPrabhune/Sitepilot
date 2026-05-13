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

            // CRITICAL: Validate site_id for number generation
            $siteId = $data['site_id'] ?? $po->site_id ?? null;
            if (!$siteId) {
                throw new \Exception('Site ID is required for GRN number generation. Per-site numbering requires a valid site_id.');
            }

            // Create GRN
            $grn = Grn::create([
                'grn_number' => Grn::generateGrnNumber($siteId), // Force override any user input
                'grn_type' => Grn::TYPE_AGAINST_PO,
                'po_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'site_id' => $po->site_id,
                'grn_date' => $data['grn_date'],
                'delivery_challan_number' => $data['delivery_challan_number'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'gate_entry_number' => $data['gate_entry_number'] ?? null,
                'delivery_challan_file' => $data['delivery_challan_file'] ?? null,
                'reference_file' => $data['reference_file'] ?? null,
                'description' => $data['description'] ?? null,
                'received_by' => $data['received_by'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'assign_to' => $data['assign_to'] ?? null,
                'status' => Grn::STATUS_COMPLETED,
                'created_by' => $data['created_by'] ?? auth()->id(),
                'workspace_id' => $data['workspace_id'] ?? getActiveWorkSpace(),
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
                    'price' => $poItem->price,
                    'remarks' => $item['remarks'] ?? null,
                ]);

                // Update PO item received quantity
                $poItem->received_qty = ($poItem->received_qty ?? 0) + $item['accepted_qty'];
                $poItem->save();
            }

            // Update PO status
            $po->updateStatusFromGrn();
            
            // Update payment flag
            $po->updatePaymentFlag();

            // Update stock immediately
            $this->stockService->addGrnStock($grn);

            // Create supplier ledger entry for GRN (informational only - no financial impact)
            try {
                LedgerHelper::createGRNEntry($grn);
            } catch (\Exception $e) {
                Log::error('Failed to create supplier ledger entry for GRN: ' . $e->getMessage());
            }

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
            // CRITICAL: Validate site_id for number generation
            $siteId = $data['site_id'] ?? null;
            if (!$siteId) {
                throw new \Exception('Site ID is required for Direct GRN number generation. Per-site numbering requires a valid site_id.');
            }

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
                'grn_number' => Grn::generateGrnNumber($siteId), // Force override any user input
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
                'delivery_challan_file' => $data['delivery_challan_file'] ?? null,
                'reference_file' => $data['reference_file'] ?? null,
                'description' => $data['description'] ?? null,
                'received_by' => $data['received_by'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'assign_to' => $data['assign_to'] ?? null,
                'tax_type' => $data['tax_type'] ?? Grn::TAX_TYPE_CGST,
                'status' => Grn::STATUS_COMPLETED,
                'created_by' => $data['created_by'] ?? auth()->id(),
                'workspace_id' => $data['workspace_id'] ?? getActiveWorkSpace(),
            ]);

            // Create GRN items
            foreach ($data['items'] as $item) {
                $grn->items()->create([
                    'po_item_id' => null,
                    'material_id' => $item['material_id'],
                    'ordered_qty' => $item['received_qty'],
                    'received_qty' => $item['received_qty'],
                    'accepted_qty' => $item['accepted_qty'],
                    'rejected_qty' => $item['received_qty'] - $item['accepted_qty'],
                    'price' => $item['price'],
                    'gst_master_id' => $item['gst_master_id'] ?? null,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            // Calculate totals
            $grn->calculateTotals();
            $grn->save();

            // Update stock immediately
            $this->stockService->addGrnStock($grn);

            // Create supplier ledger entry for Direct GRN (informational only - no financial impact)
            try {
                LedgerHelper::createGRNEntry($grn);
            } catch (\Exception $e) {
                Log::error('Failed to create supplier ledger entry for Direct GRN: ' . $e->getMessage());
            }

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
