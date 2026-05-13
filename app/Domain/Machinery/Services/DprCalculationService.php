<?php

namespace App\Domain\Machinery\Services;

/**
 * DPR Calculation Service
 * PURE function - no side effects, no DB calls
 */
class DprCalculationService
{
    /**
     * Calculate DPR values (PURE function)
     */
    public function calculate(array $dprData, array $machineryData): array
    {
        // PURE CALCULATION - No DB calls, no global state
        
        // Calculate working hours
        $workingHours = CalculationRoundingService::roundHours(
            max(0, $dprData['machine_end_reading'] - $dprData['machine_start_reading'])
        );
        
        // Calculate idle hours
        $idleHours = CalculationRoundingService::roundHours($dprData['machine_idle_reading'] ?? 0);
        
        // Calculate billable hours
        $billableHours = CalculationRoundingService::roundHours(
            max(0, $workingHours - $idleHours)
        );
        
        // Apply minimum billing for rental machinery
        $minimumBillingApplied = false;
        if ($machineryData['owned_by'] === 'rental' && isset($machineryData['minimum_billing_hours']) && $machineryData['minimum_billing_hours'] > 0) {
            $minimumHours = CalculationRoundingService::roundHours($machineryData['minimum_billing_hours']);
            if ($billableHours < $minimumHours) {
                $billableHours = $minimumHours;
                $minimumBillingApplied = true;
            }
        }
        
        // Calculate amount
        $rateSnapshot = CalculationRoundingService::roundRate($dprData['rate_snapshot']);
        $calculatedAmount = CalculationRoundingService::multiplyAndRound($billableHours, $rateSnapshot);
        
        return [
            'working_hours' => $workingHours,
            'idle_hours' => $idleHours,
            'billable_hours' => $billableHours,
            'rate_snapshot' => $rateSnapshot,
            'calculated_amount' => $calculatedAmount,
            'minimum_billing_applied' => $minimumBillingApplied,
            'calculation_version' => 1,
        ];
    }
    
    /**
     * Validate calculation inputs
     */
    public function validateInputs(array $dprData, array $machineryData): array
    {
        $errors = [];
        $warnings = [];
        
        // Validate readings
        if (isset($dprData['machine_start_reading']) && isset($dprData['machine_end_reading'])) {
            if ($dprData['machine_end_reading'] < $dprData['machine_start_reading']) {
                $errors[] = 'End reading must be greater than or equal to start reading';
            }
            
            $workingHours = $dprData['machine_end_reading'] - $dprData['machine_start_reading'];
            $idleHours = $dprData['machine_idle_reading'] ?? 0;
            
            if ($idleHours > $workingHours) {
                $errors[] = 'Idle hours cannot exceed total working hours';
            }
            
            // Warnings
            if ($workingHours > 0 && $idleHours > 0 && ($idleHours / $workingHours) > 0.3) {
                $warnings[] = 'Idle hours unusually high (>30% of working time)';
            }
            
            if ($workingHours == 0 && isset($dprData['diesel_consumption']) && $dprData['diesel_consumption'] > 0) {
                $warnings[] = 'Zero working hours but diesel consumption present';
            }
        }
        
        // Validate rate
        if (!isset($dprData['rate_snapshot']) || $dprData['rate_snapshot'] <= 0) {
            $errors[] = 'Rate snapshot must be provided and greater than 0';
        }
        
        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
    
    /**
     * Generate calculation hash for integrity validation
     */
    public function generateCalculationHash(array $dprData): string
    {
        $data = [
            'start' => $dprData['machine_start_reading'] ?? 0,
            'end' => $dprData['machine_end_reading'] ?? 0,
            'idle' => $dprData['machine_idle_reading'] ?? 0,
            'rate_snapshot' => $dprData['rate_snapshot'] ?? 0,
        ];
        
        return hash('sha256', json_encode($data));
    }
    
    /**
     * Verify calculation integrity
     */
    public function verifyIntegrity(array $dprData, string $storedHash): bool
    {
        $currentHash = $this->generateCalculationHash($dprData);
        return hash_equals($currentHash, $storedHash);
    }
}
