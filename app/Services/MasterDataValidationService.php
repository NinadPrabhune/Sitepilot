<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\Supplier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Master Data Validation Service
 * Prevents operational gaps at the data level
 */
class MasterDataValidationService
{
    /**
     * Validate machinery master data integrity
     */
    public static function validateMachinery(Machinery $machinery): array
    {
        $issues = [];
        
        // 🔴 CRITICAL: Owned machinery should not have suppliers
        if ($machinery->owned_by === 'owned' && $machinery->supplier_id) {
            $issues[] = [
                'type' => 'owned_with_supplier',
                'severity' => 'critical',
                'message' => 'Owned machinery should not have a supplier',
                'machinery_id' => $machinery->id,
                'machinery_name' => $machinery->name,
                'supplier_id' => $machinery->supplier_id,
                'fix' => 'Remove supplier_id from owned machinery',
            ];
        }
        
        // 🔴 CRITICAL: Rental machinery must have supplier
        if ($machinery->owned_by === 'rental' && !$machinery->supplier_id) {
            $issues[] = [
                'type' => 'rental_without_supplier',
                'severity' => 'critical',
                'message' => 'Rental machinery must have a supplier',
                'machinery_id' => $machinery->id,
                'machinery_name' => $machinery->name,
                'fix' => 'Assign a valid supplier to rental machinery',
            ];
        }
        
        // Validate supplier exists if assigned
        if ($machinery->supplier_id && !Supplier::find($machinery->supplier_id)) {
            $issues[] = [
                'type' => 'invalid_supplier',
                'severity' => 'high',
                'message' => 'Assigned supplier does not exist',
                'machinery_id' => $machinery->id,
                'supplier_id' => $machinery->supplier_id,
                'fix' => 'Remove or update supplier_id',
            ];
        }
        
        // Validate rate
        if (!$machinery->rate || $machinery->rate <= 0) {
            $issues[] = [
                'type' => 'invalid_rate',
                'severity' => 'high',
                'message' => 'Machinery rate must be greater than 0',
                'machinery_id' => $machinery->id,
                'rate' => $machinery->rate,
                'fix' => 'Set a valid rate',
            ];
        }
        
        // Validate minimum billing for rental
        if ($machinery->owned_by === 'rental' && (!$machinery->minimum_billing_hours || $machinery->minimum_billing_hours < 0)) {
            $issues[] = [
                'type' => 'invalid_minimum_billing',
                'severity' => 'medium',
                'message' => 'Rental machinery should have valid minimum billing hours',
                'machinery_id' => $machinery->id,
                'minimum_billing_hours' => $machinery->minimum_billing_hours,
                'fix' => 'Set minimum_billing_hours >= 0',
            ];
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'machinery_id' => $machinery->id,
            'machinery_name' => $machinery->name,
        ];
    }
    
    /**
     * Validate machinery before creation/update
     */
    public static function validateMachineryData(array $data, ?Machinery $machinery = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'owned_by' => 'required|in:owned,rental',
            'rate' => 'required|numeric|min:0.01',
            'minimum_billing_hours' => 'nullable|numeric|min:0',
            'workspace_id' => 'required|exists:work_spaces,id',
            'site_id' => 'required|exists:sites,id',
        ];
        
        // Conditional supplier validation
        if (($data['owned_by'] ?? '') === 'rental') {
            $rules['supplier_id'] = 'required|exists:suppliers,id';
        } elseif (($data['owned_by'] ?? '') === 'owned') {
            $rules['supplier_id'] = 'nullable|exists:suppliers,id';
        }
        
        $validator = Validator::make($data, $rules, [
            'supplier_id.required' => 'Supplier is required for rental machinery',
            'supplier_id.exists' => 'Selected supplier does not exist',
            'rate.min' => 'Rate must be greater than 0',
            'minimum_billing_hours.min' => 'Minimum billing hours cannot be negative',
        ]);
        
        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }
        
        // Additional business logic validation
        $issues = [];
        
        // Owned machinery should not have supplier
        if (($data['owned_by'] ?? '') === 'owned' && !empty($data['supplier_id'])) {
            $issues[] = [
                'type' => 'owned_with_supplier',
                'message' => 'Owned machinery should not have a supplier',
                'field' => 'supplier_id',
            ];
        }
        
        return [
            'valid' => empty($issues),
            'errors' => empty($issues) ? [] : ['business' => $issues],
        ];
    }
    
    /**
     * Validate all machinery in system
     */
    public static function validateAllMachinery(): array
    {
        $allIssues = [];
        $machinery = Machinery::all();
        
        foreach ($machinery as $machine) {
            $validation = self::validateMachinery($machine);
            if (!$validation['valid']) {
                $allIssues[] = $validation;
            }
        }
        
        return [
            'total_machinery' => $machinery->count(),
            'valid_machinery' => $machinery->count() - count($allIssues),
            'invalid_machinery' => count($allIssues),
            'issues' => $allIssues,
        ];
    }
    
    /**
     * Check for duplicate machinery names
     */
    public static function checkDuplicateMachineryNames(): array
    {
        $duplicates = Machinery::selectRaw('name, COUNT(*) as count')
                            ->groupBy('name')
                            ->having('count', '>', 1)
                            ->get();
        
        $issues = [];
        foreach ($duplicates as $duplicate) {
            $machines = Machinery::where('name', $duplicate->name)->get();
            $issues[] = [
                'type' => 'duplicate_name',
                'severity' => 'medium',
                'message' => "Duplicate machinery name: {$duplicate->name}",
                'count' => $duplicate->count,
                'machinery_ids' => $machines->pluck('id')->toArray(),
                'fix' => 'Rename machinery to ensure unique names',
            ];
        }
        
        return [
            'has_duplicates' => $duplicates->count() > 0,
            'duplicate_count' => $duplicates->count(),
            'issues' => $issues,
        ];
    }
    
    /**
     * Validate supplier data integrity
     */
    public static function validateSupplierData(): array
    {
        $issues = [];
        
        // Check for suppliers without machinery
        $suppliersWithoutMachinery = Supplier::whereDoesntHave('machinery')->get();
        foreach ($suppliersWithoutMachinery as $supplier) {
            $issues[] = [
                'type' => 'supplier_without_machinery',
                'severity' => 'low',
                'message' => "Supplier has no machinery assigned: {$supplier->name}",
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'fix' => 'Assign machinery to supplier or mark as inactive',
            ];
        }
        
        // Check for rental machinery without valid suppliers
        $rentalMachineryWithoutSupplier = Machinery::where('owned_by', 'rental')
                                                  ->whereNull('supplier_id')
                                                  ->orWhere('supplier_id', 0)
                                                  ->get();
        foreach ($rentalMachineryWithoutSupplier as $machine) {
            $issues[] = [
                'type' => 'rental_without_supplier',
                'severity' => 'critical',
                'message' => "Rental machinery without supplier: {$machine->name}",
                'machinery_id' => $machine->id,
                'machinery_name' => $machine->name,
                'fix' => 'Assign a valid supplier to rental machinery',
            ];
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }
    
    /**
     * Comprehensive master data validation
     */
    public static function comprehensiveValidation(): array
    {
        $machineryValidation = self::validateAllMachinery();
        $duplicateCheck = self::checkDuplicateMachineryNames();
        $supplierValidation = self::validateSupplierData();
        
        $allIssues = array_merge(
            $machineryValidation['issues'],
            $duplicateCheck['issues'],
            $supplierValidation['issues']
        );
        
        $criticalIssues = array_filter($allIssues, fn($issue) => ($issue['severity'] ?? 'medium') === 'critical');
        $highIssues = array_filter($allIssues, fn($issue) => ($issue['severity'] ?? 'medium') === 'high');
        
        return [
            'overall_valid' => empty($allIssues),
            'critical_issues_count' => count($criticalIssues),
            'high_issues_count' => count($highIssues),
            'total_issues_count' => count($allIssues),
            'machinery_summary' => $machineryValidation,
            'duplicate_summary' => $duplicateCheck,
            'supplier_summary' => $supplierValidation,
            'all_issues' => $allIssues,
            'recommendations' => self::generateRecommendations($allIssues),
        ];
    }
    
    /**
     * Generate recommendations based on issues
     */
    private static function generateRecommendations(array $issues): array
    {
        $recommendations = [];
        
        $criticalCount = count(array_filter($issues, fn($issue) => ($issue['severity'] ?? 'medium') === 'critical'));
        $highCount = count(array_filter($issues, fn($issue) => ($issue['severity'] ?? 'medium') === 'high'));
        
        if ($criticalCount > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Fix critical master data issues immediately',
                'reason' => 'Critical issues will cause operational failures',
            ];
        }
        
        if ($highCount > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Address high-priority issues before production',
                'reason' => 'High issues may cause data inconsistencies',
            ];
        }
        
        $duplicateIssues = array_filter($issues, fn($issue) => ($issue['type'] ?? '') === 'duplicate_name');
        if (!empty($duplicateIssues)) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Resolve duplicate machinery names',
                'reason' => 'Duplicates can cause confusion in reporting',
            ];
        }
        
        return $recommendations;
    }
}
