<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;
use App\Models\Supplier;
use App\Models\PaymentModuleAllocation;
use App\Services\POCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModuleCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected POCalculationService $poCalculationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poCalculationService = app(POCalculationService::class);
    }

    /**
     * Test getPOSummary with mode='po' returns PO-specific totals
     */
    public function test_getPOSummary_with_po_mode_returns_po_specific_totals()
    {
        // Create supplier with 2 POs
        $supplier = Supplier::factory()->create();
        
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 5000,
        ]);
        
        // Create invoice only for PO1
        PurchaseInvoice::factory()->create([
            'po_id' => $po1->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 8000,
        ]);
        
        // Test PO1 summary
        $response = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-summary', [
                'purchase_order_id' => $po1->id,
                'mode' => 'po',
            ]));
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(10000, $data['po_total']); // Only PO1 total
        $this->assertEquals(8000, $data['invoiced_amount']); // Only PO1 invoiced
    }

    /**
     * Test getPOSummary with mode='supplier' returns supplier-level totals (legacy)
     */
    public function test_getPOSummary_with_supplier_mode_returns_supplier_level_totals()
    {
        $supplier = Supplier::factory()->create();
        
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 5000,
        ]);
        
        // Create invoices for both POs
        PurchaseInvoice::factory()->create([
            'po_id' => $po1->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 8000,
        ]);
        
        PurchaseInvoice::factory()->create([
            'po_id' => $po2->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 4000,
        ]);
        
        // Test supplier-level summary
        $response = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-summary', [
                'purchase_order_id' => $po1->id,
                'mode' => 'supplier',
            ]));
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(15000, $data['po_total']); // Both POs
        $this->assertEquals(12000, $data['invoiced_amount']); // Both invoices
    }

    /**
     * Test getRemainingPayment with advance_against_po uses po_total - advance_paid
     */
    public function test_getRemainingPayment_with_advance_against_po_uses_correct_formula()
    {
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        // Create advance payment
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'payment_type' => 'advance_against_po',
            'amount' => 3000,
        ]);
        
        $response = $this->actingAs($this->createUserWithPermissions())
            ->post(route('payments-module.get-remaining-payment'), [
                'po_id' => $po->id,
                'payment_type' => 'advance_against_po',
                'supplier_id' => $supplier->id,
                '_token' => csrf_token(),
            ]);
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(7000, $data['remaining_payment']); // 10000 - 3000
    }

    /**
     * Test getRemainingPayment with against_po uses payable
     */
    public function test_getRemainingPayment_with_against_po_uses_payable()
    {
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        // Create invoice
        $invoice = PurchaseInvoice::factory()->create([
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 8000,
        ]);
        
        // Create payment against invoice
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'purchase_invoice_id' => $invoice->id,
            'payment_type' => 'against_po',
            'amount' => 5000,
        ]);
        
        $response = $this->actingAs($this->createUserWithPermissions())
            ->post(route('payments-module.get-remaining-payment'), [
                'po_id' => $po->id,
                'payment_type' => 'against_po',
                'supplier_id' => $supplier->id,
                '_token' => csrf_token(),
            ]);
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(3000, $data['remaining_payment']); // 8000 - 5000
    }

    /**
     * Test getPOLedger with mode='po' returns PO-specific entries only
     */
    public function test_getPOLedger_with_po_mode_returns_po_specific_entries()
    {
        $supplier = Supplier::factory()->create();
        
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 5000,
        ]);
        
        // Create payment for PO1
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po1->id,
            'payment_type' => 'advance_against_po',
            'amount' => 3000,
        ]);
        
        // Create payment for PO2
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po2->id,
            'payment_type' => 'advance_against_po',
            'amount' => 2000,
        ]);
        
        $response = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-ledger', [
                'purchase_order_id' => $po1->id,
                'mode' => 'po',
            ]));
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('success', $data['status']);
        // Should only have PO1 entries (1 PO creation + 1 payment = 2 entries)
        $this->assertCount(2, $data['entries']);
    }

    /**
     * Test getPOLedger with mode='supplier' returns supplier-level entries (legacy)
     */
    public function test_getPOLedger_with_supplier_mode_returns_supplier_level_entries()
    {
        $supplier = Supplier::factory()->create();
        
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 5000,
        ]);
        
        // Create payment for PO1
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po1->id,
            'payment_type' => 'advance_against_po',
            'amount' => 3000,
        ]);
        
        // Create payment for PO2
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po2->id,
            'payment_type' => 'advance_against_po',
            'amount' => 2000,
        ]);
        
        $response = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-ledger', [
                'purchase_order_id' => $po1->id,
                'mode' => 'supplier',
            ]));
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('success', $data['status']);
        // Should have all supplier entries (2 POs + 2 payments = 4 entries)
        $this->assertGreaterThan(2, count($data['entries']));
    }

    /**
     * Test calculations match POCalculationService results
     */
    public function test_calculations_match_po_calculation_service()
    {
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        $invoice = PurchaseInvoice::factory()->create([
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 8000,
        ]);
        
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'payment_type' => 'advance_against_po',
            'amount' => 3000,
        ]);
        
        // Get service calculation
        $serviceData = $this->poCalculationService->calculate($po->id);
        
        // Get API calculation
        $response = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-summary', [
                'purchase_order_id' => $po->id,
                'mode' => 'po',
            ]));
        
        $apiData = $response->json();
        
        $this->assertEquals($serviceData['po_total'], $apiData['po_total']);
        $this->assertEquals($serviceData['invoiced_amount'], $apiData['invoiced_amount']);
        $this->assertEquals($serviceData['total_paid'], $apiData['paid_amount']);
        $this->assertEquals($serviceData['payable'], $apiData['payable']);
    }

    /**
     * Test edge cases: no invoices, no payments, fully paid PO
     */
    public function test_edge_cases_no_invoices_no_payments_fully_paid()
    {
        $supplier = Supplier::factory()->create();
        
        // PO with no invoices
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        $response1 = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-summary', [
                'purchase_order_id' => $po1->id,
                'mode' => 'po',
            ]));
        
        $data1 = $response1->json();
        $this->assertEquals(0, $data1['invoiced_amount']);
        $this->assertEquals(0, $data1['paid_amount']);
        $this->assertEquals(0, $data1['payable']);
        
        // PO with invoice but no payments
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        PurchaseInvoice::factory()->create([
            'po_id' => $po2->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 8000,
        ]);
        
        $response2 = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-summary', [
                'purchase_order_id' => $po2->id,
                'mode' => 'po',
            ]));
        
        $data2 = $response2->json();
        $this->assertEquals(8000, $data2['invoiced_amount']);
        $this->assertEquals(0, $data2['paid_amount']);
        $this->assertEquals(8000, $data2['payable']);
    }

    /**
     * Test multi-PO supplier scenario (verify isolation between POs)
     */
    public function test_multi_po_supplier_isolation()
    {
        $supplier = Supplier::factory()->create();
        
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
        
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'grand_total' => 5000,
        ]);
        
        // Add payment only to PO1
        PaymentsModule::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po1->id,
            'payment_type' => 'advance_against_po',
            'amount' => 3000,
        ]);
        
        // PO1 should have advance_paid
        $response1 = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-summary', [
                'purchase_order_id' => $po1->id,
                'mode' => 'po',
            ]));
        
        $data1 = $response1->json();
        $this->assertEquals(3000, $data1['advance_paid']);
        
        // PO2 should have no advance_paid
        $response2 = $this->actingAs($this->createUserWithPermissions())
            ->get(route('payments-module.get-po-summary', [
                'purchase_order_id' => $po2->id,
                'mode' => 'po',
            ]));
        
        $data2 = $response2->json();
        $this->assertEquals(0, $data2['advance_paid']);
    }

    /**
     * Helper method to create user with required permissions
     */
    protected function createUserWithPermissions()
    {
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('manage-payment create');
        return $user;
    }
}
