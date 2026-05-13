<?php

namespace Database\Seeders;

use App\Services\DefaultMasterDataService;
use Illuminate\Database\Seeder;

/**
 * DefaultMasterDataSeeder
 * 
 * Seeds global master data for the application.
 * This seeder provides a convenient way to populate the following tables:
 * - document_types
 * - leave_types
 * - branches
 * - departments
 * - designations
 * 
 * Usage:
 *   php artisan db:seed --class=DefaultMasterDataSeeder
 * 
 * For force reset (truncate and reseed):
 *   Use the service directly: app(DefaultMasterDataService::class)->seedAll(true);
 * 
 * @see \App\Services\DefaultMasterDataService
 */
class DefaultMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding default master data...');
        
        $service = app(DefaultMasterDataService::class);
        $result = $service->seedAll();
        
        if ($result['success']) {
            $this->command->info($result['message']);
            
            // Display seeded data summary
            $this->displaySeededData($service);
        } else {
            $this->command->error($result['message']);
        }
    }
    
    /**
     * Display summary of seeded data
     */
    protected function displaySeededData(DefaultMasterDataService $service): void
    {
        $this->command->info('');
        $this->command->info('=== Seeded Data Summary ===');
        
        $documentTypes = $service->getDocumentTypes();
        $this->command->info('Document Types: ' . count($documentTypes));
        foreach ($documentTypes as $doc) {
            $this->command->info("  - {$doc->name}");
        }
        
        $leaveTypes = $service->getLeaveTypes();
        $this->command->info('Leave Types: ' . count($leaveTypes));
        foreach ($leaveTypes as $leave) {
            $this->command->info("  - {$leave->title} ({$leave->days} days)");
        }
        
        $branches = $service->getBranches();
        $this->command->info('Branches: ' . count($branches));
        foreach ($branches as $branch) {
            $this->command->info("  - {$branch->name}");
        }
        
        $departments = $service->getDepartments();
        $this->command->info('Departments: ' . count($departments));
        foreach ($departments as $dept) {
            $this->command->info("  - {$dept->name}");
        }
        
        $designations = $service->getDesignations();
        $this->command->info('Designations: ' . count($designations));
        foreach ($designations as $designation) {
            $this->command->info("  - {$designation->name}");
        }
        
        $this->command->info('');
    }
}
