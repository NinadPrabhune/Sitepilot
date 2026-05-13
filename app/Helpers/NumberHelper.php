<?php

namespace App\Helpers;



class NumberHelper
{
    public static function amountToWords($amount)
    {
        $formatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($amount));
    }

    /**
     * Format number in Indian (Lakhs) format
     * Example: 399000.00 becomes 3,99,000.00
     * Example: 5310000.00 becomes 53,10,000.00
     * 
     * @param float $number
     * @param int $decimals
     * @return string
     */
    public static function formatIndian($number, $decimals = 2)
    {
        // Handle negative numbers
        $isNegative = $number < 0;
        $number = abs($number);
        
        // Separate decimal part
        $decimalPart = '';
        if ($decimals > 0) {
            $decimalPart = number_format($number - floor($number), $decimals);
            $number = floor($number);
        }
        
        // Convert to string and reverse for easier processing
        $numberStr = (string)$number;
        $reversed = strrev($numberStr);
        
        // First group: 3 digits (hundreds)
        $firstGroup = substr($reversed, 0, 3);
        $remaining = substr($reversed, 3);
        
        // Remaining groups: 2 digits each (lakhs, crores, etc.)
        $groups = str_split($remaining, 2);
        
        // Combine groups
        $formatted = $firstGroup;
        foreach ($groups as $group) {
            if ($group !== '') {
                $formatted .= ',' . $group;
            }
        }
        
        // Reverse back
        $formatted = strrev($formatted);
        
        // Add decimal part
        if ($decimals > 0) {
            $formatted .= substr($decimalPart, 1); // Remove leading '0' from decimal part
        }
        
        // Add negative sign if needed
        if ($isNegative) {
            $formatted = '-' . $formatted;
        }
        
        return $formatted;
    }
}
