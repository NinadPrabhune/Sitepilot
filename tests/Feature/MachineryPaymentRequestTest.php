<?php

namespace Tests\Feature;

use App\Domain\Machinery\Enums\MachineryPaymentStatus;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentPeriod;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use App\Models\Machinery;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MachineryPaymentRequestTest extends TestCase
{
    use RefreshDatabase;
    
    protected MachineryPaymentRequestService $service;
    protected Workspace $workspace;
    protected Machinery $machinery;
    protected Supplier $supplier;
    protected User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(MachineryPaymentRequestService::class);
        $this->workspace = Workspace::factory()->create();
        $this->machinery = Machinery::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->supplier = Supplier::factory()->create();
        $this->user = User::factory()->create();
    }
    
    /** @test */
    public function it_creates_payment_request_from_ledger()
    {
        // Create ledger entries
        $this->createLedgerEntries();
        
        $paymentRequest = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        $this->assertInstanceOf(MachineryPaymentRequest::class, $paymentRequest);
        $this->assertEquals('draft', $paymentRequest->status);
        $this->assertEquals(7500.00, $paymentRequest->credits); // 5000 + 2500
        $this->assertEquals(1500.00, $paymentRequest->debits); // 1000 + 500
        $this->assertEquals(6000.00, $paymentRequest->net_payable);
        $this->assertNotEmpty($paymentRequest->audit_snapshot);
        $this->assertArrayHasKey('ledger_entry_ids', $paymentRequest->audit_snapshot);
        $this->assertArrayHasKey('entries_hash', $paymentRequest->audit_snapshot);
    }
    
    /** @test */
    public function it_blocks_period_overlap_with_locked_period()
    {
        // Create a locked period
        MachineryPaymentPeriod::create([
            'machinery_id' => $this->machinery->id,
            'workspace_id' => $this->workspace->id,
            'start_date' => '2026-01-15',
            'end_date' => '2026-01-31',
            'is_locked' => true,
            'locked_at' => now(),
            'created_by' => $this->user->id,
        ]);
        
        $this->createLedgerEntries();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment period overlaps with existing locked period');
        
        $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-10',
            '2026-01-20',
            $this->user->id
        );
    }
    
    /** @test */
    public function it_blocks_active_request_overlap()
    {
        // Create an active payment request
        MachineryPaymentRequest::create([
            'machinery_id' => $this->machinery->id,
            'supplier_id' => $this->supplier->id,
            'workspace_id' => $this->workspace->id,
            'period_start' => '2026-01-15',
            'period_end' => '2026-01-31',
            'credits' => 1000,
            'debits' => 0,
            'net_payable' => 1000,
            'status' => 'submitted',
            'audit_snapshot' => ['ledger_entry_ids' => []],
            'requested_by' => $this->user->id,
        ]);
        
        $this->createLedgerEntries();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Active payment request already exists for this period');
        
        $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-10',
            '2026-01-20',
            $this->user->id
        );
    }
    
    /** @test */
    public function it_handles_idempotency_key()
    {
        $this->createLedgerEntries();
        
        $idempotencyKey = bin2hex(random_bytes(32));
        
        $request1 = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id,
            $idempotencyKey
        );
        
        $request2 = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id,
            $idempotencyKey
        );
        
        $this->assertEquals($request1->id, $request2->id);
        $this->assertEquals($request1->idempotency_key, $request2->idempotency_key);
    }
    
    /** @test */
    public function it_sets_hold_status_for_negative_payable()
    {
        // Create only debit entries
        MachineryLedger::create([
            'machinery_id' => $this->machinery->id,
            'workspace_id' => $this->workspace->id,
            'entry_direction' => 'debit',
            'entry_type' => 'advance',
            'reference_type' => 'advance',
            'reference_id' => 1,
            'amount' => 5000.00,
            'running_balance' => -5000.00,
            'date' => '2026-01-10',
            'description' => 'Advance payment',
        ]);
        
        $paymentRequest = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        $this->assertEquals('hold', $paymentRequest->status);
        $this->assertEquals(-5000.00, $paymentRequest->net_payable);
    }
    
    /** @test */
    public function it_transitions_status_correctly()
    {
        $this->createLedgerEntries();
        
        $paymentRequest = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        // Submit
        $this->service->submit($paymentRequest->id, $this->user->id);
        $paymentRequest->refresh();
        $this->assertEquals('submitted', $paymentRequest->status);
        
        // Verify
        $this->service->verify($paymentRequest->id, $this->user->id);
        $paymentRequest->refresh();
        $this->assertEquals('verified', $paymentRequest->status);
        
        // Approve
        $this->service->approve($paymentRequest->id, $this->user->id);
        $paymentRequest->refresh();
        $this->assertEquals('approved', $paymentRequest->status);
        
        // Lock
        $this->service->lock($paymentRequest->id, $this->user->id);
        $paymentRequest->refresh();
        $this->assertEquals('locked', $paymentRequest->status);
        
        // Mark as paid
        $this->service->markAsPaid($paymentRequest->id, $this->user->id);
        $paymentRequest->refresh();
        $this->assertEquals('paid', $paymentRequest->status);
    }
    
    /** @test */
    public function it_blocks_invalid_status_transitions()
    {
        $this->createLedgerEntries();
        
        $paymentRequest = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot approve request in status: draft');
        
        $this->service->approve($paymentRequest->id, $this->user->id);
    }
    
    /** @test */
    public function it_blocks_approval_on_calculation_mismatch()
    {
        $this->createLedgerEntries();
        
        $paymentRequest = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        $this->service->submit($paymentRequest->id, $this->user->id);
        
        // Modify a ledger entry to change calculation
        MachineryLedger::where('machinery_id', $this->machinery->id)
            ->where('entry_direction', 'credit')
            ->first()
            ->update(['amount' => 10000.00]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ledger calculation has changed since payment request was created');
        
        $this->service->verify($paymentRequest->id, $this->user->id);
    }
    
    /** @test */
    public function it_links_ledger_entries_on_approval()
    {
        $this->createLedgerEntries();
        
        $paymentRequest = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        $this->service->submit($paymentRequest->id, $this->user->id);
        $this->service->verify($paymentRequest->id, $this->user->id);
        $this->service->approve($paymentRequest->id, $this->user->id);
        
        $linkedCount = MachineryLedger::where('payment_request_id', $paymentRequest->id)->count();
        $this->assertGreaterThan(0, $linkedCount);
    }
    
    /** @test */
    public function it_prevents_double_spend()
    {
        $this->createLedgerEntries();
        
        $request1 = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        $this->service->submit($request1->id, $this->user->id);
        $this->service->verify($request1->id, $this->user->id);
        $this->service->approve($request1->id, $this->user->id);
        
        // Try to create another request with overlapping period
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Active payment request already exists for this period');
        
        $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-15',
            '2026-02-15',
            $this->user->id
        );
    }
    
    /** @test */
    public function it_rejects_payment_request()
    {
        $this->createLedgerEntries();
        
        $paymentRequest = $this->service->createFromLedger(
            $this->machinery->id,
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            $this->user->id
        );
        
        $this->service->reject($paymentRequest->id, $this->user->id, 'Test rejection');
        
        $paymentRequest->refresh();
        $this->assertEquals('rejected', $paymentRequest->status);
        $this->assertEquals('Test rejection', $paymentRequest->remarks);
    }
    
    private function createLedgerEntries(): void
    {
        $entries = [
            [
                'date' => '2026-01-05',
                'entry_direction' => 'credit',
                'entry_type' => 'reading',
                'reference_type' => 'reading',
                'reference_id' => 1,
                'amount' => 5000.00,
                'description' => 'Monthly reading payment',
            ],
            [
                'date' => '2026-01-10',
                'entry_direction' => 'credit',
                'entry_type' => 'diesel',
                'reference_type' => 'diesel',
                'reference_id' => 1,
                'amount' => 2500.00,
                'description' => 'Diesel charge',
            ],
            [
                'date' => '2026-01-08',
                'entry_direction' => 'debit',
                'entry_type' => 'advance',
                'reference_type' => 'advance',
                'reference_id' => 1,
                'amount' => 1000.00,
                'description' => 'Advance payment deducted',
            ],
            [
                'date' => '2026-01-20',
                'entry_direction' => 'debit',
                'entry_type' => 'transfer',
                'reference_type' => 'transfer',
                'reference_id' => 1,
                'amount' => 500.00,
                'description' => 'Transfer deduction',
            ],
        ];
        
        $runningBalance = 0;
        
        foreach ($entries as $entry) {
            $amount = $entry['amount'];
            
            if ($entry['entry_direction'] === 'credit') {
                $runningBalance += $amount;
            } else {
                $runningBalance -= $amount;
            }
            
            MachineryLedger::create([
                'machinery_id' => $this->machinery->id,
                'workspace_id' => $this->workspace->id,
                'entry_direction' => $entry['entry_direction'],
                'entry_type' => $entry['entry_type'],
                'reference_type' => $entry['reference_type'],
                'reference_id' => $entry['reference_id'],
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'date' => $entry['date'],
                'description' => $entry['description'],
            ]);
        }
    }
}
