<?php

namespace App\Services;

use App\Models\StockTransaction;
use App\Models\MaterialProjectStock;
use App\Models\Grn;
use App\Models\GrnItem;
use Illuminate\Support\Facades\DB;
use Exception;

class StockService
{
    /**
     * Add opening stock for a material at a project/site.
     *
     * @param int $projectId
     * @param int $materialId
     * @param float $quantity
     * @param float|null $rate
     * @param string|null $remarks
     * @return StockTransaction
     * @throws Exception
     */
    public function addOpeningStock($projectId, $materialId, $quantity, $rate = null, $remarks = null)
    {
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than zero');
        }

        return DB::transaction(function () use ($projectId, $materialId, $quantity, $rate, $remarks) {
            // Create ledger record
            $transaction = StockTransaction::create([
                'project_id' => $projectId,
                'material_id' => $materialId,
                'type' => StockTransaction::TYPE_OPENING,
                'quantity' => $quantity,
                'rate' => $rate,
                'remarks' => $remarks,
                'created_by' => auth()->id(),
            ]);

            // Update current stock
            $this->updateCurrentStock($projectId, $materialId, $quantity);

            return $transaction;
        });
    }

    /**
     * Add GRN stock (receipt from purchase).
     *
     * @param Grn $grn
     * @return array
     * @throws Exception
     */
    public function addGrnStock(Grn $grn)
    {
        $transactions = [];

        return DB::transaction(function () use ($grn, &$transactions) {
            foreach ($grn->items as $item) {
                // Create ledger record
                $transaction = StockTransaction::create([
                    'project_id' => $grn->site_id,
                    'material_id' => $item->material_id,
                    'type' => StockTransaction::TYPE_GRN,
                    'quantity' => $item->accepted_qty,
                    'rate' => $item->price,
                    'reference_type' => 'grn',
                    'reference_id' => $grn->id,
                    'remarks' => 'GRN Receipt: ' . $grn->grn_number,
                    'created_by' => $grn->created_by,
                ]);

                $transactions[] = $transaction;

                // Update current stock
                $this->updateCurrentStock($grn->site_id, $item->material_id, $item->accepted_qty);
            }

            return $transactions;
        });
    }

    /**
     * Issue material from stock.
     *
     * @param int $projectId
     * @param int $materialId
     * @param float $quantity
     * @param string|null $remarks
     * @param string|null $referenceType
     * @param int|null $referenceId
     * @return StockTransaction
     * @throws Exception
     */
    public function issueMaterial($projectId, $materialId, $quantity, $remarks = null, $referenceType = null, $referenceId = null)
    {
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than zero');
        }

        // Check available stock
        $availableStock = $this->getCurrentStock($projectId, $materialId);
        if ($availableStock < $quantity) {
            throw new Exception('Insufficient stock. Available: ' . $availableStock . ', Requested: ' . $quantity);
        }

        return DB::transaction(function () use ($projectId, $materialId, $quantity, $remarks, $referenceType, $referenceId) {
            // Create ledger record with negative quantity
            $transaction = StockTransaction::create([
                'project_id' => $projectId,
                'material_id' => $materialId,
                'type' => StockTransaction::TYPE_ISSUE,
                'quantity' => -$quantity,
                'rate' => null,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'remarks' => $remarks,
                'created_by' => auth()->id(),
            ]);

            // Update current stock (deduct)
            $this->updateCurrentStock($projectId, $materialId, -$quantity);

            return $transaction;
        });
    }

    /**
     * Transfer stock from one project to another.
     *
     * @param int $fromProjectId
     * @param int $toProjectId
     * @param int $materialId
     * @param float $quantity
     * @param string|null $remarks
     * @return array
     * @throws Exception
     */
    public function transferStock($fromProjectId, $toProjectId, $materialId, $quantity, $remarks = null)
    {
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than zero');
        }

        // Check available stock at source
        $availableStock = $this->getCurrentStock($fromProjectId, $materialId);
        if ($availableStock < $quantity) {
            throw new Exception('Insufficient stock at source project. Available: ' . $availableStock . ', Requested: ' . $quantity);
        }

        return DB::transaction(function () use ($fromProjectId, $toProjectId, $materialId, $quantity, $remarks) {
            $userId = auth()->id();

            // Create transfer out record
            $transactionOut = StockTransaction::create([
                'project_id' => $fromProjectId,
                'material_id' => $materialId,
                'type' => StockTransaction::TYPE_TRANSFER_OUT,
                'quantity' => -$quantity,
                'rate' => null,
                'reference_type' => 'transfer',
                'reference_id' => null,
                'remarks' => $remarks ?: 'Transfer to project ID: ' . $toProjectId,
                'created_by' => $userId,
            ]);

            // Create transfer in record
            $transactionIn = StockTransaction::create([
                'project_id' => $toProjectId,
                'material_id' => $materialId,
                'type' => StockTransaction::TYPE_TRANSFER_IN,
                'quantity' => $quantity,
                'rate' => null,
                'reference_type' => 'transfer',
                'reference_id' => null,
                'remarks' => $remarks ?: 'Transfer from project ID: ' . $fromProjectId,
                'created_by' => $userId,
            ]);

            // Update stock at both projects
            $this->updateCurrentStock($fromProjectId, $materialId, -$quantity);
            $this->updateCurrentStock($toProjectId, $materialId, $quantity);

            return [
                'out' => $transactionOut,
                'in' => $transactionIn,
            ];
        });
    }

    /**
     * Adjust stock (for corrections).
     *
     * @param int $projectId
     * @param int $materialId
     * @param float $quantity (positive for increase, negative for decrease)
     * @param string|null $remarks
     * @return StockTransaction
     * @throws Exception
     */
    public function adjustStock($projectId, $materialId, $quantity, $remarks = null)
    {
        return DB::transaction(function () use ($projectId, $materialId, $quantity, $remarks) {
            // Create ledger record
            $transaction = StockTransaction::create([
                'project_id' => $projectId,
                'material_id' => $materialId,
                'type' => StockTransaction::TYPE_ADJUSTMENT,
                'quantity' => $quantity,
                'rate' => null,
                'reference_type' => null,
                'reference_id' => null,
                'remarks' => $remarks ?: 'Stock adjustment',
                'created_by' => auth()->id(),
            ]);

            // Update current stock
            $this->updateCurrentStock($projectId, $materialId, $quantity);

            return $transaction;
        });
    }

    /**
     * Update current stock for a material at a project.
     *
     * @param int $projectId
     * @param int $materialId
     * @param float $quantityChange (positive or negative)
     */
    public function updateCurrentStock($projectId, $materialId, $quantityChange)
    {
        $stock = MaterialProjectStock::firstOrCreate(
            ['project_id' => $projectId, 'material_id' => $materialId],
            ['current_stock' => 0]
        );

        $stock->current_stock += $quantityChange;
        $stock->save();
    }

    /**
     * Get current stock for a material at a project.
     *
     * @param int $projectId
     * @param int $materialId
     * @return float
     */
    public function getCurrentStock($projectId, $materialId)
    {
        return MaterialProjectStock::getCurrentStock($projectId, $materialId);
    }

    /**
     * Get stock report for all materials at a project.
     *
     * @param int|null $projectId
     * @param int|null $materialId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStockReport($projectId = null, $materialId = null)
    {
        $query = MaterialProjectStock::with(['project', 'material']);

        if ($projectId) {
            $query->forProject($projectId);
        }

        if ($materialId) {
            $query->forMaterial($materialId);
        }

        return $query->orderBy('project_id')->orderBy('material_id')->get();
    }

    /**
     * Get stock ledger transactions.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLedgerTransactions($filters = [])
    {
        $query = StockTransaction::with(['project', 'material', 'creator']);

        if (isset($filters['project_id']) && $filters['project_id']) {
            $query->forProject($filters['project_id']);
        }

        if (isset($filters['material_id']) && $filters['material_id']) {
            $query->forMaterial($filters['material_id']);
        }

        if (isset($filters['type']) && $filters['type']) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['start_date']) || isset($filters['end_date'])) {
            $query->dateRange(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            );
        }

        return $query->latestFirst()->get();
    }

    /**
     * Validate if stock is available.
     *
     * @param int $projectId
     * @param int $materialId
     * @param float $quantity
     * @return bool
     */
    public function isStockAvailable($projectId, $materialId, $quantity)
    {
        $availableStock = $this->getCurrentStock($projectId, $materialId);
        return $availableStock >= $quantity;
    }

    /**
     * Reverse GRN stock (for cancellation).
     *
     * @param Grn $grn
     * @return array
     * @throws Exception
     */
    public function reverseGrnStock(Grn $grn)
    {
        $transactions = [];

        return DB::transaction(function () use ($grn, &$transactions) {
            foreach ($grn->items as $item) {
                // Create reversal ledger record
                $transaction = StockTransaction::create([
                    'project_id' => $grn->site_id,
                    'material_id' => $item->material_id,
                    'type' => StockTransaction::TYPE_ADJUSTMENT,
                    'quantity' => -$item->accepted_qty,
                    'rate' => $item->price,
                    'reference_type' => 'grn_reversal',
                    'reference_id' => $grn->id,
                    'remarks' => 'GRN Reversal: ' . $grn->grn_number,
                    'created_by' => auth()->id(),
                ]);

                $transactions[] = $transaction;

                // Update current stock (reverse the addition)
                $this->updateCurrentStock($grn->site_id, $item->material_id, -$item->accepted_qty);
            }

            return $transactions;
        });
    }
}
