<?php

namespace App\Domain\Machinery\Services;

/**
 * Calculation Rounding Service
 * Centralized rounding to prevent decimal drift
 */
class CalculationRoundingService
{
    private const DECIMAL_PLACES = 2;
    
    /**
     * Round amount to 2 decimal places
     */
    public static function roundAmount($amount): float
    {
        return round($amount, self::DECIMAL_PLACES, PHP_ROUND_HALF_UP);
    }
    
    /**
     * Round hours to 2 decimal places
     */
    public static function roundHours($hours): float
    {
        return round($hours, self::DECIMAL_PLACES, PHP_ROUND_HALF_UP);
    }
    
    /**
     * Round rate to 2 decimal places
     */
    public static function roundRate($rate): float
    {
        return round($rate, self::DECIMAL_PLACES, PHP_ROUND_HALF_UP);
    }
    
    /**
     * Round decimal using bcmath for precision
     */
    public static function roundDecimal($value): string
    {
        return bcadd($value, '0', self::DECIMAL_PLACES);
    }
    
    /**
     * Multiply two numbers with proper rounding
     */
    public static function multiplyAndRound($a, $b): float
    {
        return self::roundAmount($a * $b);
    }
    
    /**
     * Add two numbers with proper rounding
     */
    public static function addAndRound($a, $b): float
    {
        return self::roundAmount($a + $b);
    }
    
    /**
     * Subtract two numbers with proper rounding
     */
    public static function subtractAndRound($a, $b): float
    {
        return self::roundAmount($a - $b);
    }
    
    /**
     * Get decimal places constant
     */
    public static function getDecimalPlaces(): int
    {
        return self::DECIMAL_PLACES;
    }
}
