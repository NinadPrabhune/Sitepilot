<?php

/**
 * Sample Data Script for Supplier Ledger Report Testing
 * 
 * This script inserts sample data to test the Supplier Ledger Report.
 * It focuses on creating:
 * 1. Purchase Invoices (to create DEBIT entries)
 * 2. Payments (to create CREDIT entries)
 * 3. Supplier Transactions (ledger entries)
 * 
 * Run with: php artisan tinker --execute="include 'database/seeders/sample_supplier_ledger_data.php';"
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Configuration - Using existing data from database
$workspaceId = 2;  // Next-Gen Solar Solutions
$createdBy = 1;    // Super Admin
$projectId = 2;    // Nisarg Residency

// For the script to work outside Laravel, we'll use direct SQL
// This script should be run via: php artisan tinker < sample_data.php
// Or better: include this in a seeder

/*
 * ========================================================================
 * SAMPLE DATA FOR SUPPLIER LEDGER REPORT
 * ========================================================================
 * 
 * The Supplier Ledger Report tracks:
 * - Total Purchases (from Invoices - DEBIT)
 * - Total Payments (from Payments - CREDIT)
 * - Current Balance
 * 
 * We create sample data with 3 different suppliers showing different scenarios:
 * 
 * 1. Shivam Constructions - Partially Paid (Balance: 31,000)
 * 2. Raj Materials - Fully Paid (Balance: 0)
 * 3. ACE Equipment - Outstanding (Balance: 31,000)
 * 
 * ========================================================================
 */

// Start transaction
DB::beginTransaction();

try {
    
    // Get supplier IDs
    $supplier1Id = 1; // Shivam Constructions
    $supplier2Id = 2; // Raj Materials  
    $supplier3Id = 3; // ACE Equipment Rentals
    
    $now = now();
    
    // ====================================================================
    // CHAIN 1: Shivam Constructions - Partially Paid
    // ====================================================================
    
    // 1. Purchase Invoice (DEBIT entry - amount owed to supplier)
    $invoice1Id = DB::table('purchase_invoices')->insertGetId([
        'invoice_number' => 'INV-2026-0001',
        'invoice_date' => '2026-01-22',
        'supplier_id' => $supplier1Id,
        'supplier_invoice_number' => 'SUP-INV-001',
        'total_amount' => 62000.00,
        'total_taxable_value' => 50000.00,
        'total_cgst' => 6000.00,
        'total_sgst' => 6000.00,
        'total_igst' => 0.00,
        'total_tax' => 12000.00,
        'total_discount' => 0.00,
        'grand_total' => 62000.00,
        'status' => 'Approved',
        'site_id' => $projectId,
        'created_by' => $createdBy,
        'workspace_id' => $workspaceId,
        'payment_status' => 'unpaid',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // 2. Payment - Partial (CREDIT entry - amount paid to supplier)
    $payment1Id = DB::table('payments_module')->insertGetId([
        'payment_number' => 'PAY-2026-0001',
        'supplier_id' => $supplier1Id,
        'purchase_invoice_id' => $invoice1Id,
        'site_id' => $projectId,
        'payment_date' => '2026-02-05',
        'amount' => 31000.00,
        'payment_type' => 'against_invoice',
        'mode' => 'Bank Transfer',
        'reference_number' => 'UTR-123456',
        'created_by' => $createdBy,
        'workspace_id' => $workspaceId,
        'notes' => 'Partial payment - 50%',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // Payment Allocation
    DB::table('payment_module_allocations')->insert([
        'payment_module_id' => $payment1Id,
        'purchase_invoice_id' => $invoice1Id,
        'allocated_amount' => 31000.00,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // 3. Supplier Ledger Entries
    // Invoice entry - DEBIT (balance increases)
    DB::table('supplier_transactions')->insert([
        'supplier_id' => $supplier1Id,
        'site_id' => $projectId,
        'reference_type' => 'invoice',
        'reference_id' => $invoice1Id,
        'transaction_date' => '2026-01-22',
        'debit' => 62000.00,
        'credit' => 0.00,
        'balance' => 62000.00,
        'description' => 'Purchase Invoice INV-2026-0001 - 62,000',
        'workspace_id' => $workspaceId,
        'created_by' => $createdBy,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // Payment entry - CREDIT (balance decreases)
    DB::table('supplier_transactions')->insert([
        'supplier_id' => $supplier1Id,
        'site_id' => $projectId,
        'reference_type' => 'payment',
        'reference_id' => $payment1Id,
        'transaction_date' => '2026-02-05',
        'debit' => 0.00,
        'credit' => 31000.00,
        'balance' => 31000.00,
        'description' => 'Supplier Payment PAY-2026-0001 - 31,000 (Partial)',
        'workspace_id' => $workspaceId,
        'created_by' => $createdBy,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    echo "Chain 1 (Shivam Constructions) completed: Invoice 62,000, Payment 31,000, Balance 31,000\n";
    
    // ====================================================================
    // CHAIN 2: Raj Materials - Fully Paid
    // ====================================================================
    
    // 1. Purchase Invoice (DEBIT entry)
    $invoice2Id = DB::table('purchase_invoices')->insertGetId([
        'invoice_number' => 'INV-2026-0002',
        'invoice_date' => '2026-02-20',
        'supplier_id' => $supplier2Id,
        'supplier_invoice_number' => 'SUP-INV-002',
        'total_amount' => 37200.00,
        'total_taxable_value' => 30000.00,
        'total_cgst' => 3600.00,
        'total_sgst' => 3600.00,
        'total_igst' => 0.00,
        'total_tax' => 7200.00,
        'total_discount' => 0.00,
        'grand_total' => 37200.00,
        'status' => 'Approved',
        'site_id' => $projectId,
        'created_by' => $createdBy,
        'workspace_id' => $workspaceId,
        'payment_status' => 'paid',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // 2. Advance Payment (made before invoice)
    $advancePaymentId = DB::table('payments_module')->insertGetId([
        'payment_number' => 'PAY-2026-0002',
        'supplier_id' => $supplier2Id,
        'purchase_invoice_id' => null,
        'site_id' => $projectId,
        'payment_date' => '2026-02-08',
        'amount' => 10000.00,
        'payment_type' => 'advance',
        'mode' => 'Bank Transfer',
        'reference_number' => 'UTR-789012',
        'created_by' => $createdBy,
        'workspace_id' => $workspaceId,
        'notes' => 'Advance payment',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // 3. Final Payment (CREDIT entry)
    $payment2Id = DB::table('payments_module')->insertGetId([
        'payment_number' => 'PAY-2026-0003',
        'supplier_id' => $supplier2Id,
        'purchase_invoice_id' => $invoice2Id,
        'site_id' => $projectId,
        'payment_date' => '2026-03-01',
        'amount' => 27200.00,
        'payment_type' => 'against_invoice',
        'mode' => 'Bank Transfer',
        'reference_number' => 'UTR-345678',
        'created_by' => $createdBy,
        'workspace_id' => $workspaceId,
        'notes' => 'Final payment after advance adjustment',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // Payment Allocations
    DB::table('payment_module_allocations')->insert([
        'payment_module_id' => $payment2Id,
        'purchase_invoice_id' => $invoice2Id,
        'allocated_amount' => 27200.00,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // 4. Supplier Ledger Entries
    // Advance entry - CREDIT (negative balance initially)
    DB::table('supplier_transactions')->insert([
        'supplier_id' => $supplier2Id,
        'site_id' => $projectId,
        'reference_type' => 'advance',
        'reference_id' => $advancePaymentId,
        'transaction_date' => '2026-02-08',
        'debit' => 0.00,
        'credit' => 10000.00,
        'balance' => -10000.00,
        'description' => 'Advance Payment PAY-2026-0002 - 10,000',
        'workspace_id' => $workspaceId,
        'created_by' => $createdBy,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // Invoice entry - DEBIT
    DB::table('supplier_transactions')->insert([
        'supplier_id' => $supplier2Id,
        'site_id' => $projectId,
        'reference_type' => 'invoice',
        'reference_id' => $invoice2Id,
        'transaction_date' => '2026-02-20',
        'debit' => 37200.00,
        'credit' => 0.00,
        'balance' => 27200.00,
        'description' => 'Purchase Invoice INV-2026-0002 - 37,200',
        'workspace_id' => $workspaceId,
        'created_by' => $createdBy,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // Payment entry - CREDIT
    DB::table('supplier_transactions')->insert([
        'supplier_id' => $supplier2Id,
        'site_id' => $projectId,
        'reference_type' => 'payment',
        'reference_id' => $payment2Id,
        'transaction_date' => '2026-03-01',
        'debit' => 0.00,
        'credit' => 27200.00,
        'balance' => 0.00,
        'description' => 'Supplier Payment PAY-2026-0003 - 27,200 (Settled)',
        'workspace_id' => $workspaceId,
        'created_by' => $createdBy,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    echo "Chain 2 (Raj Materials) completed: Advance 10,000, Invoice 37,200, Payment 27,200, Balance 0\n";
    
    // ====================================================================
    // CHAIN 3: ACE Equipment - Outstanding (No Payment)
    // ====================================================================
    
    // 1. Purchase Invoice (DEBIT entry - outstanding)
    $invoice3Id = DB::table('purchase_invoices')->insertGetId([
        'invoice_number' => 'INV-2026-0003',
        'invoice_date' => '2026-03-01',
        'supplier_id' => $supplier3Id,
        'supplier_invoice_number' => 'SUP-INV-003',
        'total_amount' => 31000.00,
        'total_taxable_value' => 25000.00,
        'total_cgst' => 3000.00,
        'total_sgst' => 3000.00,
        'total_igst' => 0.00,
        'total_tax' => 6000.00,
        'total_discount' => 0.00,
        'grand_total' => 31000.00,
        'status' => 'Approved',
        'site_id' => $projectId,
        'created_by' => $createdBy,
        'workspace_id' => $workspaceId,
        'payment_status' => 'unpaid',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    // 2. Supplier Ledger Entry (No payment made yet)
    DB::table('supplier_transactions')->insert([
        'supplier_id' => $supplier3Id,
        'site_id' => $projectId,
        'reference_type' => 'invoice',
        'reference_id' => $invoice3Id,
        'transaction_date' => '2026-03-01',
        'debit' => 31000.00,
        'credit' => 0.00,
        'balance' => 31000.00,
        'description' => 'Purchase Invoice INV-2026-0003 - 31,000 (Outstanding)',
        'workspace_id' => $workspaceId,
        'created_by' => $createdBy,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    
    echo "Chain 3 (ACE Equipment) completed: Invoice 31,000, Payment 0, Balance 31,000 (Outstanding)\n";
    
    // ====================================================================
    // SUMMARY
    // ====================================================================
    
    echo "\n========================================\n";
    echo "=== SAMPLE DATA CREATED SUCCESSFULLY ===\n";
    echo "========================================\n\n";
    
    echo "SUPPLIER LEDGER SUMMARY:\n";
    echo "------------------------\n";
    echo "1. Shivam Constructions: Invoice 62,000, Paid 31,000, Balance 31,000 (Partial)\n";
    echo "2. Raj Materials:       Invoice 37,200, Paid 37,200, Balance 0 (Settled)\n";
    echo "3. ACE Equipment:      Invoice 31,000, Paid 0,     Balance 31,000 (Outstanding)\n\n";
    echo "Total Purchases:   130,200\n";
    echo "Total Payments:    68,200\n";
    echo "Outstanding:       62,000\n\n";
    echo "========================================\n";
    echo "View the Supplier Ledger Report at:\n";
    echo "/reports-supplier-ledger\n";
    echo "========================================\n";
    
    DB::commit();
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    throw $e;
}
