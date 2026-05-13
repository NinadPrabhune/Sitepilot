<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\WorkSpace;
use App\Models\Setting;
use App\Models\Plan;
use App\Models\Language;
use App\Models\Currency;
use App\Models\Unit;
use App\Models\MaterialCategory;
use App\Models\Material;
use App\Models\SupplierCategory;
use App\Models\Supplier;
use App\Models\ManPowerType;
use App\Models\MachineryCategory;
use App\Models\Machinery;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PaymentsModule;
use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\MaterialTransfer;
use App\Models\Activity;
use App\Models\Project;
use Workdo\Taskly\Entities\Project as TasklyProject;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class DataRecoverySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive data recovery...');
        
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // 1. Restore Core Settings and Configuration
        $this->restoreCoreSettings();
        
        // 2. Restore Master Data
        $this->restoreMasterData();
        
        // 3. Restore Business Data
        $this->restoreBusinessData();
        
        // 4. Restore Sample Transactions
        $this->restoreTransactions();
        
        // 5. Restore Activity Logs
        $this->restoreActivities();
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('✅ Data recovery completed successfully!');
    }
    
    private function restoreCoreSettings()
    {
        $this->command->info('📋 Restoring core settings...');
        
        // Create default workspace
        $workspace = WorkSpace::firstOrCreate([
            'name' => 'Default Workspace',
            'slug' => 'default-workspace',
            'created_by' => 1,
        ]);
        
        // Update existing users to belong to workspace
        User::where('workspace_id', null)->update(['workspace_id' => $workspace->id]);
        
        // Restore default settings
        $defaultSettings = [
            ['key' => 'company_name', 'value' => 'SitePilot ERP'],
            ['key' => 'company_email', 'value' => 'info@sitepilot.com'],
            ['key' => 'company_phone', 'value' => '+91-9876543210'],
            ['key' => 'company_address', 'value' => '123 Business Park, Mumbai, India'],
            ['key' => 'default_currency', 'value' => 'INR'],
            ['key' => 'date_format', 'value' => 'd-m-Y'],
            ['key' => 'time_format', 'value' => 'H:i'],
            ['key' => 'timezone', 'value' => 'Asia/Kolkata'],
        ];
        
        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate($setting);
        }
        
        $this->command->info('✅ Core settings restored');
    }
    
    private function restoreMasterData()
    {
        $this->command->info('📚 Restoring master data...');
        
        // Units
        $units = [
            ['name' => 'Kilograms', 'symbol' => 'KG', 'is_active' => 1],
            ['name' => 'Metric Tons', 'symbol' => 'MT', 'is_active' => 1],
            ['name' => 'Bags', 'symbol' => 'BAG', 'is_active' => 1],
            ['name' => 'Cubic Meters', 'symbol' => 'CBM', 'is_active' => 1],
            ['name' => 'Liters', 'symbol' => 'LTR', 'is_active' => 1],
            ['name' => 'Pieces', 'symbol' => 'PCS', 'is_active' => 1],
            ['name' => 'Hours', 'symbol' => 'HRS', 'is_active' => 1],
            ['name' => 'Days', 'symbol' => 'DAYS', 'is_active' => 1],
        ];
        
        foreach ($units as $unit) {
            Unit::firstOrCreate(['symbol' => $unit['symbol']], $unit);
        }
        
        // Material Categories
        $categories = [
            ['name' => 'Cement'],
            ['name' => 'Steel'],
            ['name' => 'Aggregates'],
            ['name' => 'Bricks'],
            ['name' => 'Electrical'],
            ['name' => 'Plumbing'],
            ['name' => 'Paints'],
            ['name' => 'Wood'],
        ];
        
        foreach ($categories as $category) {
            MaterialCategory::firstOrCreate(['name' => $category['name']], $category);
        }
        
        // Materials
        $materials = [
            ['name' => 'Cement 43 Grade', 'category_id' => 1, 'unit_id' => 1, 'price' => 350, 'hsn_sac' => '25232990', 'sku' => 'CEM43'],
            ['name' => 'Cement 53 Grade', 'category_id' => 1, 'unit_id' => 1, 'price' => 380, 'hsn_sac' => '25232990', 'sku' => 'CEM53'],
            ['name' => 'Steel TMT 12mm', 'category_id' => 2, 'unit_id' => 1, 'price' => 65, 'hsn_sac' => '73089090', 'sku' => 'STL12'],
            ['name' => 'Steel TMT 16mm', 'category_id' => 2, 'unit_id' => 1, 'price' => 62, 'hsn_sac' => '73089090', 'sku' => 'STL16'],
            ['name' => 'River Sand', 'category_id' => 3, 'unit_id' => 2, 'price' => 1200, 'hsn_sac' => '25050000', 'sku' => 'SAND'],
            ['name' => 'Crushed Stone', 'category_id' => 3, 'unit_id' => 2, 'price' => 950, 'hsn_sac' => '25171000', 'sku' => 'STONE'],
            ['name' => 'Red Bricks', 'category_id' => 4, 'unit_id' => 5, 'price' => 8, 'hsn_sac' => '69010000', 'sku' => 'BRICK'],
            ['name' => 'AAC Blocks', 'category_id' => 4, 'unit_id' => 5, 'price' => 45, 'hsn_sac' => '69010000', 'sku' => 'AAC'],
        ];
        
        foreach ($materials as $material) {
            Material::firstOrCreate(['sku' => $material['sku']], $material);
        }
        
        // Supplier Categories
        $supplierCategories = [
            ['name' => 'Material Suppliers'],
            ['name' => 'Machinery Suppliers'],
            ['name' => 'Labor Contractors'],
            ['name' => 'Transport Contractors'],
            ['name' => 'Electrical Contractors'],
            ['name' => 'Plumbing Contractors'],
        ];
        
        foreach ($supplierCategories as $category) {
            SupplierCategory::firstOrCreate(['name' => $category['name']], $category);
        }
        
        // Manpower Types
        $manpowerTypes = [
            ['name' => 'Mason'],
            ['name' => 'Carpenter'],
            ['name' => 'Electrician'],
            ['name' => 'Plumber'],
            ['name' => 'Painter'],
            ['name' => 'Helper'],
            ['name' => 'Welder'],
            ['name' => 'Operator'],
        ];
        
        foreach ($manpowerTypes as $type) {
            ManPowerType::firstOrCreate(['name' => $type['name']], $type);
        }
        
        // Machinery Categories
        $machineryCategories = [
            ['name' => 'Earth Moving'],
            ['name' => 'Concrete'],
            ['name' => 'Lifting'],
            ['name' => 'Transport'],
            ['name' => 'Compaction'],
            ['name' => 'Power Tools'],
        ];
        
        foreach ($machineryCategories as $category) {
            MachineryCategory::firstOrCreate(['name' => $category['name']], $category);
        }
        
        $this->command->info('✅ Master data restored');
    }
    
    private function restoreBusinessData()
    {
        $this->command->info('💼 Restoring business data...');
        
        // Suppliers
        $suppliers = [
            [
                'name' => 'UltraTech Cement Ltd',
                'category_id' => 1,
                'type' => 'material',
                'contact_person' => 'Rajesh Kumar',
                'phone' => '9876543210',
                'email' => 'rajesh@ultratech.com',
                'address' => 'Mumbai, Maharashtra',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
                'country' => 'India',
                'gst_number' => '27AAAPU1234C1ZV',
                'is_active' => 1,
            ],
            [
                'name' => 'Tata Steel Ltd',
                'category_id' => 1,
                'type' => 'material',
                'contact_person' => 'Amit Singh',
                'phone' => '9876543211',
                'email' => 'amit@tatasteel.com',
                'address' => 'Jamshedpur, Jharkhand',
                'city' => 'Jamshedpur',
                'state' => 'Jharkhand',
                'pincode' => '831001',
                'country' => 'India',
                'gst_number' => '20AAACT2912D1ZV',
                'is_active' => 1,
            ],
            [
                'name' => 'ABC Machinery Rentals',
                'category_id' => 2,
                'type' => 'machinery',
                'contact_person' => 'Vijay Patel',
                'phone' => '9876543212',
                'email' => 'vijay@abcrentals.com',
                'address' => 'Ahmedabad, Gujarat',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'pincode' => '380001',
                'country' => 'India',
                'gst_number' => '24AAAPC1234B1ZV',
                'is_active' => 1,
            ],
        ];
        
        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['name' => $supplier['name']], $supplier);
        }
        
        // Machinery
        $machinery = [
            [
                'name' => 'JCB 3DX',
                'category_id' => 1,
                'owned_by' => 'owned',
                'model_number' => '3DX',
                'manufacturer' => 'JCB',
                'status' => 1,
            ],
            [
                'name' => 'Concrete Pump',
                'category_id' => 2,
                'owned_by' => 'rental',
                'model_number' => 'CP-30',
                'manufacturer' => 'Sany',
                'status' => 1,
            ],
            [
                'name' => 'Tower Crane',
                'category_id' => 3,
                'owned_by' => 'rental',
                'model_number' => 'TC-6015',
                'manufacturer' => 'Liebherr',
                'status' => 1,
            ],
            [
                'name' => 'Vibratory Roller',
                'category_id' => 5,
                'owned_by' => 'owned',
                'model_number' => 'VR-100',
                'manufacturer' => 'Bomag',
                'status' => 1,
            ],
        ];
        
        foreach ($machinery as $machine) {
            Machinery::firstOrCreate(['name' => $machine['name']], $machine);
        }
        
        // Projects
        $projects = [
            [
                'project_name' => 'Commercial Complex Phase 1',
                'client_name' => 'ABC Developers',
                'location' => 'Bandra, Mumbai',
                'start_date' => '2026-01-15',
                'end_date' => '2026-12-31',
                'contract_value' => 50000000,
                'status' => 'active',
                'created_by' => 1,
            ],
            [
                'project_name' => 'Residential Tower A',
                'client_name' => 'XYZ Builders',
                'location' => 'Powai, Mumbai',
                'start_date' => '2026-02-01',
                'end_date' => '2027-03-31',
                'contract_value' => 75000000,
                'status' => 'active',
                'created_by' => 1,
            ],
        ];
        
        foreach ($projects as $project) {
            TasklyProject::firstOrCreate(['project_name' => $project['project_name']], $project);
        }
        
        $this->command->info('✅ Business data restored');
    }
    
    private function restoreTransactions()
    {
        $this->command->info('💰 Restoring sample transactions...');
        
        // Sample Purchase Orders
        $purchaseOrders = [
            [
                'po_number' => 'PO-2026-001',
                'supplier_id' => 1,
                'project_id' => 1,
                'order_date' => '2026-04-15',
                'delivery_date' => '2026-04-20',
                'total_amount' => 350000,
                'status' => 'received',
                'created_by' => 1,
            ],
            [
                'po_number' => 'PO-2026-002',
                'supplier_id' => 2,
                'project_id' => 1,
                'order_date' => '2026-04-18',
                'delivery_date' => '2026-04-25',
                'total_amount' => 425000,
                'status' => 'pending',
                'created_by' => 1,
            ],
        ];
        
        foreach ($purchaseOrders as $po) {
            PurchaseOrder::firstOrCreate(['po_number' => $po['po_number']], $po);
        }
        
        // Sample Purchase Invoices
        $purchaseInvoices = [
            [
                'invoice_number' => 'INV-2026-001',
                'supplier_id' => 1,
                'project_id' => 1,
                'invoice_date' => '2026-04-20',
                'due_date' => '2026-05-20',
                'total_amount' => 350000,
                'tax_amount' => 63000,
                'net_amount' => 413000,
                'status' => 'pending_payment',
                'created_by' => 1,
            ],
        ];
        
        foreach ($purchaseInvoices as $invoice) {
            PurchaseInvoice::firstOrCreate(['invoice_number' => $invoice['invoice_number']], $invoice);
        }
        
        // Sample Payments
        $payments = [
            [
                'payment_number' => 'PAY-2026-001',
                'supplier_id' => 1,
                'purchase_invoice_id' => 1,
                'payment_date' => '2026-04-25',
                'amount' => 200000,
                'payment_type' => 'advance',
                'mode' => 'bank_transfer',
                'reference_number' => 'BANK-2026-001',
                'status' => 'approved',
                'created_by' => 1,
            ],
        ];
        
        foreach ($payments as $payment) {
            PaymentsModule::firstOrCreate(['payment_number' => $payment['payment_number']], $payment);
        }
        
        $this->command->info('✅ Sample transactions restored');
    }
    
    private function restoreActivities()
    {
        $this->command->info('📝 Restoring activity logs...');
        
        $activities = [
            [
                'title' => 'Foundation Excavation',
                'description' => 'Excavation work for foundation',
                'project_id' => 1,
                'assign_to' => 1,
                'start_date' => '2026-04-01',
                'due_date' => '2026-04-15',
                'status' => 'completed',
                'created_by' => 1,
            ],
            [
                'title' => 'Column Casting',
                'description' => 'RCC column casting work',
                'project_id' => 1,
                'assign_to' => 2,
                'start_date' => '2026-04-16',
                'due_date' => '2026-04-30',
                'status' => 'in_progress',
                'created_by' => 1,
            ],
            [
                'title' => 'Slab Formwork',
                'description' => 'Formwork for ground floor slab',
                'project_id' => 2,
                'assign_to' => 1,
                'start_date' => '2026-04-20',
                'due_date' => '2026-05-05',
                'status' => 'pending',
                'created_by' => 1,
            ],
        ];
        
        foreach ($activities as $activity) {
            Activity::firstOrCreate(['title' => $activity['title']], $activity);
        }
        
        $this->command->info('✅ Activity logs restored');
    }
}
