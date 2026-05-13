<?php

namespace Tests\FinancialIntegrity\Traits;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\PaymentsModule;
use Illuminate\Support\Facades\DB;

trait FinancialIntegrityAssertions
{
    /**
     * Assert financial integrity for a given machinery payment request
     */
    protected function assertFinancialIntegrity(int $requestId, array $expectedState = []): void
    {
        $request = MachineryPaymentRequest::find($requestId);
        if (!$request) {
            $this->fail("Machinery payment request #{$requestId} not found");
        }

        // Get actual DB state
        $actualState = [
            'total_payments' => $request->payments()->count(),
            'posted_payments' => $request->payments()->posted()->count(),
            'posted_total' => $request->payments()->posted()->sum('amount'),
            'duplicate_payments' => $this->countDuplicatePayments($requestId),
            'orphan_linkages' => $this->countOrphanLinkages($requestId),
            'settlement_status' => $request->settlement_status,
        ];

        // Verify no duplicate payments
        $this->assertEquals(0, $actualState['duplicate_payments'], 
            "Duplicate payments detected: {$actualState['duplicate_payments']}");

        // Verify no orphan linkages
        $this->assertEquals(0, $actualState['orphan_linkages'], 
            "Orphan linkages detected: {$actualState['orphan_linkages']}");

        // Verify posted total doesn't exceed payable
        $this->assertLessThanOrEqual($request->net_payable, $actualState['posted_total'],
            "Posted total ({$actualState['posted_total']}) exceeds payable ({$request->net_payable})");

        // Settlement status consistency
        $expectedSettlementStatus = $this->calculateExpectedSettlementStatus($actualState['posted_total'], $request->net_payable);
        $this->assertEquals($expectedSettlementStatus, $actualState['settlement_status'],
            "Settlement status mismatch: expected '{$expectedSettlementStatus}', got '{$actualState['settlement_status']}'");

        // Apply custom expectations if provided
        foreach ($expectedState as $key => $value) {
            $this->assertEquals($value, $actualState[$key] ?? null,
                "Financial integrity assertion failed for '{$key}': expected {$value}, got " . ($actualState[$key] ?? 'null'));
        }

        $this->info("✅ Financial integrity verified for request #{$requestId}");
    }

    /**
     * Count duplicate payments for a request
     */
    protected function countDuplicatePayments(int $requestId): int
    {
        $duplicates = DB::select("
            SELECT integration_reference_uuid, COUNT(*) as count
            FROM payments_module 
            WHERE source_type = 'machinery_payment_request' 
            AND source_id = ?
            AND integration_reference_uuid IS NOT NULL
            GROUP BY integration_reference_uuid
            HAVING count > 1
        ", [$requestId]);

        return count($duplicates);
    }

    /**
     * Count orphan linkages (payments without proper machinery payment request)
     */
    protected function countOrphanLinkages(int $requestId): int
    {
        return PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $requestId)
            ->whereDoesntHave('machineryPaymentRequest')
            ->count();
    }

    /**
     * Calculate expected settlement status
     */
    protected function calculateExpectedSettlementStatus(float $postedTotal, float $netPayable): string
    {
        if ($postedTotal == 0) return 'unpaid';
        if (bccomp($postedTotal, $netPayable, 2) < 0) return 'partial';
        if (bccomp($postedTotal, $netPayable, 2) === 0) return 'paid';
        return 'overpaid';
    }

    /**
     * Create DB snapshot for audit purposes
     */
    protected function createDbSnapshot(string $snapshotName): array
    {
        $snapshot = [
            'name' => $snapshotName,
            'timestamp' => now()->toISOString(),
            'payments_module' => DB::table('payments_module')->count(),
            'machinery_payment_requests' => DB::table('machinery_payment_requests')->count(),
            'integration_audit_logs' => DB::table('integration_audit_logs')->count(),
        ];

        // Store snapshot details for verification
        $this->snapshots[$snapshotName] = $snapshot;

        $this->info("📸 DB Snapshot '{$snapshotName}' created");
        return $snapshot;
    }

    /**
     * Compare DB snapshots
     */
    protected function assertDbSnapshotIntegrity(string $beforeSnapshot, string $afterSnapshot, array $expectedChanges = []): void
    {
        $before = $this->snapshots[$beforeSnapshot] ?? null;
        $after = $this->snapshots[$afterSnapshot] ?? null;

        if (!$before || !$after) {
            $this->fail("Snapshot not found: '{$beforeSnapshot}' or '{$afterSnapshot}'");
        }

        foreach ($expectedChanges as $table => $expectedChange) {
            $actualChange = ($after[$table] ?? 0) - ($before[$table] ?? 0);
            $this->assertEquals($expectedChange, $actualChange,
                "DB change mismatch for table '{$table}': expected {$expectedChange}, got {$actualChange}");
        }

        $this->info("✅ DB snapshot integrity verified between '{$beforeSnapshot}' and '{$afterSnapshot}'");
    }

    /**
     * Verify idempotency with physical DB row count
     */
    protected function assertIdempotencyWithPhysicalCount(string $integrationReference, int $requestId, int $expectedCount = 1): void
    {
        $actualCount = PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $requestId)
            ->where('integration_reference_uuid', $integrationReference)
            ->count();

        $this->assertEquals($expectedCount, $actualCount,
            "Idempotency violation: expected {$expectedCount} rows for integration reference '{$integrationReference}', found {$actualCount}");

        $this->info("✅ Idempotency verified: {$actualCount} rows for integration reference '{$integrationReference}'");
    }

    /**
     * Helper method to output test information
     */
    protected function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }

    /**
     * Store snapshots for comparison
     */
    protected array $snapshots = [];
}
