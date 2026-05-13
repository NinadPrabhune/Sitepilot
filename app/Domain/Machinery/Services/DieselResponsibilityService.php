<?php

namespace App\Domain\Machinery\Services;

use App\Models\Machinery;

/**
 * Centralized service for determining diesel cost responsibility.
 * 
 * This service provides a single source of truth for diesel payment logic,
 * ensuring consistency across billing and payment workflows.
 */
class DieselResponsibilityService
{
    /**
     * Determine if company bears the diesel cost for this machinery.
     * 
     * @param Machinery $machinery The machinery record
     * @return bool True if company pays diesel, false if supplier pays
     */
    public static function companyPaysDiesel(Machinery $machinery): bool
    {
        return $machinery->diesel_by_company === true;
    }
    
    /**
     * Determine if diesel should be deducted from supplier payment.
     * Returns true if company pays diesel (deduct from supplier payment).
     * 
     * @param Machinery $machinery The machinery record
     * @return bool True if diesel should be deducted from payment
     */
    public static function shouldDeductDieselFromPayment(Machinery $machinery): bool
    {
        return self::companyPaysDiesel($machinery);
    }
    
    /**
     * Calculate diesel amount to deduct from supplier payment.
     * Returns 0 if supplier pays, actual cost if company pays.
     * 
     * @param Machinery $machinery The machinery record
     * @param float $dieselCost Total diesel cost in ₹ (already converted from liters)
     * @return float Amount to deduct (0 if supplier pays)
     */
    public static function getDeductibleDieselAmount(Machinery $machinery, float $dieselCost): float
    {
        return self::companyPaysDiesel($machinery) ? $dieselCost : 0;
    }
}
