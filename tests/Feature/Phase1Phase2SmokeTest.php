<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;
use App\Models\PaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1Phase2SmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that invoicing columns exist on purchase_orders table
     */
    public function test_purchase_orders_has_invoicing_columns(): void
    {
        $this->assertTrue(
            \Schema::hasColumn('purchase_orders', 'invoiced_amount'),
            'purchase_orders table should have invoiced_amount column'
        );

        $this->assertTrue(
            \Schema::hasColumn('purchase_orders', 'invoiced_status'),
            'purchase_orders table should have invoiced_status column'
        );
    }

    /**
     * Test that payment_flag has been deprecated
     */
    public function test_payment_flag_is_deprecated(): void
    {
        $this->assertFalse(
            \Schema::hasColumn('purchase_orders', 'payment_flag'),
            'purchase_orders table should NOT have payment_flag column (deprecated)'
        );

        $this->assertTrue(
            \Schema::hasColumn('purchase_orders', 'payment_flag_deprecated'),
            'purchase_orders table should have payment_flag_deprecated column'
        );
    }

    /**
     * Test that purchase_invoice_id has index on payments_module
     */
    public function test_payments_module_has_purchase_invoice_index(): void
    {
        $this->assertTrue(
            \Schema::hasColumn('payments_module', 'purchase_invoice_id'),
            'payments_module table should have purchase_invoice_id column'
        );

        // Check if index exists (MySQL specific)
        $indexes = \DB::select("SHOW INDEX FROM payments_module WHERE Key_name = 'idx_purchase_invoice_id'");
        $this->assertNotEmpty($indexes, 'payments_module should have index on purchase_invoice_id');
    }

    /**
     * Test that PurchaseOrder model has new methods
     */
    public function test_purchase_order_has_invoicing_methods(): void
    {
        $po = new PurchaseOrder();

        $this->assertTrue(
            method_exists($po, 'updateInvoicedStatus'),
            'PurchaseOrder should have updateInvoicedStatus method'
        );

        $this->assertTrue(
            method_exists($po, 'getInvoicedStatusDisplay'),
            'PurchaseOrder should have getInvoicedStatusDisplay method'
        );

        $this->assertTrue(
            method_exists($po, 'scopeInvoicingEligible'),
            'PurchaseOrder should have scopeInvoicingEligible scope'
        );
    }

    /**
     * Test that PaymentsModule validates purchase_invoice_id for against_invoice payments
     */
    public function test_payments_module_validates_invoice_id_for_invoice_payments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('purchase_invoice_id is required for against_invoice payments');

        PaymentsModule::create([
            'payment_number' => 'TEST-001',
            'supplier_id' => 1,
            'purchase_invoice_id' => null, // This should fail
            'payment_date' => now(),
            'amount' => 1000.00,
            'payment_type' => PaymentsModule::PAYMENT_TYPE_AGAINST_INVOICE,
            'status' => PaymentsModule::STATUS_COMPLETED,
            'created_by' => 1,
            'workspace_id' => 1,
        ]);
    }

    /**
     * Test that invoicing columns are backfilled correctly
     */
    public function test_invoicing_columns_backfilled(): void
    {
        // Create a PO
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'po_date' => now(),
            'supplier_id' => 1,
            'grand_total' => 10000.00,
            'status' => 'approved',
            'site_id' => 1,
            'created_by' => 1,
            'workspace_id' => 1,
        ]);

        // Create an invoice for this PO
        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'INV-TEST-001',
            'invoice_date' => now(),
            'supplier_id' => 1,
            'po_id' => $po->id,
            'grand_total' => 5000.00,
            'status' => 'approved',
            'site_id' => 1,
            'created_by' => 1,
            'workspace_id' => 1,
        ]);

        // Update invoicing status
        $po->updateInvoicedStatus();

        // Refresh from database
        $po->refresh();

        $this->assertEquals(5000.00, $po->invoiced_amount);
        $this->assertEquals('partially_invoiced', $po->invoiced_status);
    }

    /**
     * Test that audit log channel exists
     */
    public function test_payment_audit_log_channel_exists(): void
    {
        $config = config('logging.channels.payment_audit');
        $this->assertNotNull($config, 'payment_audit log channel should exist');
        $this->assertEquals('daily', $config['driver']);
    }

    /**
     * Test baseline data can be queried
     */
    public function test_baseline_queries_execute(): void
    {
        // Test PO-based payments query
        $poPayments = PaymentsModule::whereIn('payment_type', ['against_po', 'advance_against_po'])
            ->count();
        $this->assertIsInt($poPayments);

        // Test payment allocations query
        $allocations = \DB::table('payment_module_allocations')->count();
        $this->assertIsInt($allocations);

        // Test direct GRN invoices query
        $directInvoices = PurchaseInvoice::whereNull('po_id')->count();
        $this->assertIsInt($directInvoices);
    }

    /**
     * Test that deprecated methods still work (backward compatibility)
     */
    public function test_deprecated_methods_backward_compatibility(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-002',
            'po_date' => now(),
            'supplier_id' => 1,
            'grand_total' => 10000.00,
            'status' => 'approved',
            'site_id' => 1,
            'created_by' => 1,
            'workspace_id' => 1,
        ]);

        // Deprecated method should still work
        $po->updatePaymentFlag();
        $po->refresh();

        $this->assertNotNull($po->payment_flag_deprecated);
    }
}
