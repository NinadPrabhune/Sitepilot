<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateExistingMachineryPaymentNumbers extends Seeder
{
    /**
     * Update existing machinery payment records with proper payment numbers
     */
    public function run(): void
    {
        $this->command->info('Updating existing machinery payment records...');
        
        // Get all records without payment numbers
        $records = DB::table('machinery_payment_requests')
            ->whereNull('payment_number')
            ->orderBy('created_at', 'asc')
            ->get();
        
        if ($records->isEmpty()) {
            $this->command->info('No records found without payment numbers.');
            return;
        }
        
        $this->command->info("Found {$records->count()} records to update.");
        
        foreach ($records as $record) {
            // Assign site_id based on workspace_id (default to site 1 for now)
            if (empty($record->site_id)) {
                // For now, assign site_id = 1 (you can modify this logic based on your business rules)
                DB::table('machinery_payment_requests')
                    ->where('id', $record->id)
                    ->update(['site_id' => 1]);
                
                $this->command->info("Assigned site_id = 1 to record ID: {$record->id}");
            }
            
            // Generate payment number using the numbering service
            try {
                $paymentNumber = app(\App\Services\NumberGeneratorService::class)
                    ->generate('machinery_payment', $record->site_id ?? 1);
                
                // Update the record with the payment number
                DB::table('machinery_payment_requests')
                    ->where('id', $record->id)
                    ->update(['payment_number' => $paymentNumber]);
                
                $this->command->info("Updated record ID: {$record->id} with payment number: {$paymentNumber}");
                
            } catch (\Exception $e) {
                $this->command->error("Failed to generate payment number for record ID: {$record->id}. Error: " . $e->getMessage());
                Log::error("Failed to update machinery payment number", [
                    'record_id' => $record->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->command->info('Machinery payment number update completed.');
    }
}
