<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateRequiredERPData extends Command
{
    protected $signature = 'machinery:create-erp-data';
    protected $description = 'Create minimal ERP data for machinery payment integration testing';

    public function handle()
    {
        $this->info('🏗️  Creating Minimal ERP Data for Integration Testing');
        $this->line('===============================================');

        try {
            // Create project (site)
            $projectId = DB::table('projects')->insertGetId([
                'name' => 'Test Project for Machinery Integration',
                'status' => 'Ongoing',
                'type' => 'project',
                'currency' => '₹',
                'budget' => 1000000,
                'is_active' => 1,
                'project_progress' => 'false',
                'progress' => '0',
                'task_progress' => 'true',
                'estimated_hrs' => 100,
                'workspace' => 1,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("✅ Created project #{$projectId}");

            // Create supplier category first
            $categoryId = DB::table('supplier_categories')->insertGetId([
                'name' => 'Machinery Services',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create supplier
            $supplierId = DB::table('suppliers')->insertGetId([
                'name' => 'Test Supplier for Machinery',
                'category_id' => $categoryId,
                'email' => 'test@machinery.com',
                'phone' => '1234567890',
                'site_id' => $projectId,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("✅ Created supplier #{$supplierId}");

            // Create purchase invoice
            $invoiceId = DB::table('purchase_invoices')->insertGetId([
                'invoice_number' => 'INV-MACH-' . time(),
                'supplier_id' => $supplierId,
                'site_id' => $projectId,
                'invoice_date' => now()->format('Y-m-d'),
                'total_amount' => 100000.00,
                'grand_total' => 100000.00,
                'status' => 'Pending',
                'workspace_id' => 1,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("✅ Created purchase invoice #{$invoiceId}");

            $this->line('');
            $this->info('📋 ERP Data Summary:');
            $this->line("  Project ID: {$projectId}");
            $this->line("  Supplier ID: {$supplierId}");
            $this->line("  Invoice ID: {$invoiceId}");
            $this->line("  Workspace ID: 1 (existing)");

            $this->line('');
            $this->info('🎯 Ready for machinery payment integration testing');
            $this->line('  Use these IDs in your integration payload');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create ERP data: " . $e->getMessage());
            return 1;
        }
    }
}
