<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use Illuminate\Support\Facades\DB;

/**
 * Historical Suggestion Service
 * Provides auto-suggestions based on historical behavior patterns
 */
class HistoricalSuggestionService
{
    /**
     * Get intelligent suggestions for DPR data
     */
    public static function getDprSuggestions(array $data, int $userId = null): array
    {
        $suggestions = [
            'idle_hours' => [],
            'operator_count' => [],
            'working_hours' => [],
            'rate' => [],
            'patterns' => [],
        ];

        $machineryId = $data['machinery_id'] ?? null;
        $date = $data['date'] ?? null;

        if ($machineryId && $date) {
            // Get historical patterns for this machinery
            $patterns = self::getMachineryHistoricalPatterns($machineryId, $date);
            $suggestions['patterns'] = $patterns;

            // Idle hours suggestions
            if (isset($data['machine_start_reading']) && isset($data['machine_end_reading'])) {
                $workingHours = $data['machine_end_reading'] - $data['machine_start_reading'];
                $suggestions['idle_hours'] = self::getIdleHourSuggestions($machineryId, $workingHours, $patterns);
            }

            // Operator count suggestions
            $suggestions['operator_count'] = self::getOperatorCountSuggestions($machineryId, $patterns);

            // Working hours validation
            $suggestions['working_hours'] = self::getWorkingHoursValidation($machineryId, $date, $patterns);

            // Rate suggestions
            $suggestions['rate'] = self::getRateSuggestions($machineryId, $date, $data['override_rate'] ?? null);
        }

        return $suggestions;
    }

    /**
     * Get historical patterns for machinery
     */
    private static function getMachineryHistoricalPatterns(int $machineryId, string $date): array
    {
        $patterns = [
            'typical_working_hours' => 0,
            'typical_idle_hours' => 0,
            'typical_idle_percentage' => 0,
            'typical_operator_count' => 0,
            'recent_entries' => [],
            'yesterday_entry' => null,
            'weekly_average' => [],
        ];

        // Get last 30 days of data
        $recentDprs = DailyProgressReport::where('machinery_id', $machineryId)
                                       ->where('date', '<', $date)
                                       ->orderBy('date', 'desc')
                                       ->limit(30)
                                       ->get();

        if ($recentDprs->isEmpty()) {
            return $patterns;
        }

        // Calculate averages
        $totalWorkingHours = 0;
        $totalIdleHours = 0;
        $totalOperators = 0;
        $validEntries = 0;

        foreach ($recentDprs as $dpr) {
            $workingHours = ($dpr->machine_end_reading ?? 0) - ($dpr->machine_start_reading ?? 0);
            $idleHours = $dpr->machine_idle_reading ?? 0;
            
            if ($workingHours > 0) {
                $totalWorkingHours += $workingHours;
                $totalIdleHours += $idleHours;
                $totalOperators += $dpr->number_of_operators ?? 0;
                $validEntries++;
            }

            // Store recent entries
            if (count($patterns['recent_entries']) < 7) {
                $patterns['recent_entries'][] = [
                    'date' => $dpr->date,
                    'working_hours' => $workingHours,
                    'idle_hours' => $idleHours,
                    'operator_count' => $dpr->number_of_operators ?? 0,
                    'idle_percentage' => $workingHours > 0 ? round(($idleHours / $workingHours) * 100, 1) : 0,
                ];
            }
        }

        if ($validEntries > 0) {
            $patterns['typical_working_hours'] = round($totalWorkingHours / $validEntries, 1);
            $patterns['typical_idle_hours'] = round($totalIdleHours / $validEntries, 1);
            $patterns['typical_idle_percentage'] = $patterns['typical_working_hours'] > 0 ? 
                round(($patterns['typical_idle_hours'] / $patterns['typical_working_hours']) * 100, 1) : 0;
            $patterns['typical_operator_count'] = round($totalOperators / $validEntries, 1);
        }

        // Get yesterday's entry
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $patterns['yesterday_entry'] = DailyProgressReport::where('machinery_id', $machineryId)
                                                        ->where('date', $yesterday)
                                                        ->first();

        // Calculate weekly averages
        $weeklyData = [];
        for ($i = 0; $i < 4; $i++) {
            $weekStart = date('Y-m-d', strtotime($date . " -" . (($i * 7) + 7) . " days"));
            $weekEnd = date('Y-m-d', strtotime($date . " -" . ($i * 7) . " days"));
            
            $weekDprs = DailyProgressReport::where('machinery_id', $machineryId)
                                          ->whereBetween('date', [$weekStart, $weekEnd])
                                          ->get();
            
            if ($weekDprs->isNotEmpty()) {
                $weekTotal = 0;
                $weekIdle = 0;
                foreach ($weekDprs as $dpr) {
                    $workingHours = ($dpr->machine_end_reading ?? 0) - ($dpr->machine_start_reading ?? 0);
                    $weekTotal += $workingHours;
                    $weekIdle += $dpr->machine_idle_reading ?? 0;
                }
                
                $weeklyData[] = [
                    'week' => $i + 1,
                    'average_working_hours' => round($weekTotal / count($weekDprs), 1),
                    'average_idle_hours' => round($weekIdle / count($weekDprs), 1),
                ];
            }
        }
        
        $patterns['weekly_average'] = $weeklyData;

        return $patterns;
    }

    /**
     * Get idle hour suggestions
     */
    private static function getIdleHourSuggestions(int $machineryId, float $workingHours, array $patterns): array
    {
        $suggestions = [];

        if ($patterns['typical_idle_hours'] > 0) {
            $typicalIdlePercentage = $patterns['typical_idle_percentage'];
            
            $suggestions[] = [
                'type' => 'typical',
                'message' => "Typical idle for this machine: {$patterns['typical_idle_hours']} hrs ({$typicalIdlePercentage}%)",
                'recommended' => round($workingHours * ($typicalIdlePercentage / 100), 1),
                'confidence' => 'high',
            ];
        }

        // Yesterday's comparison
        if ($patterns['yesterday_entry']) {
            $yesterdayIdle = $patterns['yesterday_entry']->machine_idle_reading ?? 0;
            $yesterdayWorking = ($patterns['yesterday_entry']->machine_end_reading ?? 0) - ($patterns['yesterday_entry']->machine_start_reading ?? 0);
            $yesterdayPercentage = $yesterdayWorking > 0 ? round(($yesterdayIdle / $yesterdayWorking) * 100, 1) : 0;
            
            $suggestions[] = [
                'type' => 'yesterday',
                'message' => "Yesterday: {$yesterdayIdle} hrs ({$yesterdayPercentage}%)",
                'recommended' => round($workingHours * ($yesterdayPercentage / 100), 1),
                'confidence' => 'medium',
            ];
        }

        // Recent trend
        if (!empty($patterns['recent_entries'])) {
            $recentIdle = array_column($patterns['recent_entries'], 'idle_hours');
            $avgRecentIdle = round(array_sum($recentIdle) / count($recentIdle), 1);
            
            $suggestions[] = [
                'type' => 'recent_trend',
                'message' => "Recent average: {$avgRecentIdle} hrs",
                'recommended' => $avgRecentIdle,
                'confidence' => 'medium',
            ];
        }

        return $suggestions;
    }

    /**
     * Get operator count suggestions
     */
    private static function getOperatorCountSuggestions(int $machineryId, array $patterns): array
    {
        $suggestions = [];

        if ($patterns['typical_operator_count'] > 0) {
            $suggestions[] = [
                'type' => 'typical',
                'message' => "Typical operators for this machine: {$patterns['typical_operator_count']}",
                'recommended' => $patterns['typical_operator_count'],
                'confidence' => 'high',
            ];
        }

        // Most common operator count
        if (!empty($patterns['recent_entries'])) {
            $operatorCounts = array_column($patterns['recent_entries'], 'operator_count');
            $countFrequency = array_count_values($operatorCounts);
            arsort($countFrequency);
            $mostCommon = key($countFrequency);
            
            $suggestions[] = [
                'type' => 'most_common',
                'message' => "Most common: {$mostCommon} operators",
                'recommended' => $mostCommon,
                'confidence' => 'medium',
            ];
        }

        return $suggestions;
    }

    /**
     * Get working hours validation
     */
    private static function getWorkingHoursValidation(int $machineryId, string $date, array $patterns): array
    {
        $validation = [
            'is_unusual' => false,
            'message' => '',
            'suggestions' => [],
        ];

        if ($patterns['typical_working_hours'] > 0) {
            // Check if current working hours are unusual
            // This would be called with actual current working hours
            // For now, provide validation framework
        }

        return $validation;
    }

    /**
     * Get rate suggestions
     */
    private static function getRateSuggestions(int $machineryId, string $date, $currentRate = null): array
    {
        $suggestions = [];

        // Get machinery standard rate
        $machinery = \App\Models\Machinery::find($machineryId);
        if ($machinery) {
            $standardRate = $machinery->rate;
            
            $suggestions[] = [
                'type' => 'standard',
                'message' => "Standard rate: ₹{$standardRate}/hr",
                'recommended' => $standardRate,
                'confidence' => 'high',
            ];
        }

        // Get recent rate history
        $recentRate = DB::table('machinery_rate_history')
                        ->where('machinery_id', $machineryId)
                        ->where('effective_from', '<=', $date)
                        ->orderBy('effective_from', 'desc')
                        ->first();

        if ($recentRate) {
            $suggestions[] = [
                'type' => 'historical',
                'message' => "Rate on this date: ₹{$recentRate->rate}/hr",
                'recommended' => $recentRate->rate,
                'confidence' => 'high',
            ];
        }

        // Check for rate override pattern
        if ($currentRate && isset($standardRate)) {
            $deviation = (($currentRate - $standardRate) / $standardRate) * 100;
            
            if (abs($deviation) > 10) {
                $suggestions[] = [
                    'type' => 'deviation_warning',
                    'message' => "Rate deviation: " . round($deviation, 1) . "% from standard",
                    'recommended' => $standardRate,
                    'confidence' => 'high',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get diesel entry suggestions
     */
    public static function getDieselSuggestions(array $data, int $userId = null): array
    {
        $suggestions = [
            'quantity' => [],
            'consumption_rate' => [],
            'patterns' => [],
        ];

        $machineryId = $data['machinery_id'] ?? null;
        $date = $data['date'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if ($machineryId && $date) {
            // Get diesel consumption patterns
            $patterns = self::getDieselConsumptionPatterns($machineryId, $date);
            $suggestions['patterns'] = $patterns;

            // Quantity suggestions
            if ($quantity) {
                $suggestions['quantity'] = self::getDieselQuantitySuggestions($machineryId, $quantity, $patterns);
            }

            // Consumption rate suggestions
            $suggestions['consumption_rate'] = self::getConsumptionRateSuggestions($machineryId, $patterns);
        }

        return $suggestions;
    }

    /**
     * Get diesel consumption patterns
     */
    private static function getDieselConsumptionPatterns(int $machineryId, string $date): array
    {
        $patterns = [
            'typical_quantity' => 0,
            'typical_consumption_rate' => 0,
            'recent_entries' => [],
            'weekly_average' => [],
        ];

        // Get recent diesel entries
        $recentDiesel = DailyConsumptionMaster::where('machinery_id', $machineryId)
                                            ->where('date', '<', $date)
                                            ->orderBy('date', 'desc')
                                            ->limit(30)
                                            ->get();

        if ($recentDiesel->isEmpty()) {
            return $patterns;
        }

        $totalQuantity = 0;
        $validEntries = 0;
        $consumptionRates = [];

        foreach ($recentDiesel as $diesel) {
            $totalQuantity += $diesel->quantity;
            $validEntries++;

            // Get corresponding DPR for working hours
            $dpr = DailyProgressReport::where('machinery_id', $machineryId)
                                     ->where('date', $diesel->date)
                                     ->first();
            
            if ($dpr) {
                $workingHours = ($dpr->machine_end_reading ?? 0) - ($dpr->machine_start_reading ?? 0);
                if ($workingHours > 0) {
                    $consumptionRate = $diesel->quantity / $workingHours;
                    $consumptionRates[] = $consumptionRate;
                }
            }

            // Store recent entries
            if (count($patterns['recent_entries']) < 7) {
                $patterns['recent_entries'][] = [
                    'date' => $diesel->date,
                    'quantity' => $diesel->quantity,
                    'consumption_rate' => end($consumptionRates) ?? 0,
                ];
            }
        }

        if ($validEntries > 0) {
            $patterns['typical_quantity'] = round($totalQuantity / $validEntries, 1);
            
            if (!empty($consumptionRates)) {
                $patterns['typical_consumption_rate'] = round(array_sum($consumptionRates) / count($consumptionRates), 2);
            }
        }

        return $patterns;
    }

    /**
     * Get diesel quantity suggestions
     */
    private static function getDieselQuantitySuggestions(int $machineryId, float $quantity, array $patterns): array
    {
        $suggestions = [];

        if ($patterns['typical_quantity'] > 0) {
            $suggestions[] = [
                'type' => 'typical',
                'message' => "Typical diesel quantity: {$patterns['typical_quantity']}L",
                'recommended' => $patterns['typical_quantity'],
                'confidence' => 'high',
            ];
        }

        // Range suggestion
        if ($patterns['typical_quantity'] > 0) {
            $minRange = round($patterns['typical_quantity'] * 0.8);
            $maxRange = round($patterns['typical_quantity'] * 1.2);
            
            $suggestions[] = [
                'type' => 'range',
                'message' => "Typical range: {$minRange}L - {$maxRange}L",
                'recommended' => $patterns['typical_quantity'],
                'confidence' => 'medium',
            ];
        }

        return $suggestions;
    }

    /**
     * Get consumption rate suggestions
     */
    private static function getConsumptionRateSuggestions(int $machineryId, array $patterns): array
    {
        $suggestions = [];

        if ($patterns['typical_consumption_rate'] > 0) {
            $suggestions[] = [
                'type' => 'typical',
                'message' => "Typical consumption: {$patterns['typical_consumption_rate']}L/hr",
                'recommended' => $patterns['typical_consumption_rate'],
                'confidence' => 'high',
            ];
        }

        return $suggestions;
    }

    /**
     * Format suggestions for UI display
     */
    public static function formatSuggestionsForUi(array $suggestions): array
    {
        $formatted = [];

        foreach ($suggestions as $category => $categorySuggestions) {
            if (!empty($categorySuggestions)) {
                $formatted[$category] = array_map(function ($suggestion) {
                    return [
                        'message' => $suggestion['message'],
                        'recommended_value' => $suggestion['recommended'] ?? null,
                        'confidence' => $suggestion['confidence'] ?? 'medium',
                        'type' => $suggestion['type'] ?? 'info',
                    ];
                }, $categorySuggestions);
            }
        }

        return $formatted;
    }

    /**
     * Get user-specific suggestions based on their patterns
     */
    public static function getUserSpecificSuggestions(int $userId, string $context): array
    {
        $userPatterns = ReasonIntelligenceService::analyzeUserReasonPatterns($userId, '30_days');
        
        $suggestions = [
            'quality_tips' => [],
            'behavioral_hints' => [],
            'personalized_guidance' => [],
        ];

        // Quality tips based on user's reason quality
        if ($userPatterns['legitimate_rate'] < 70) {
            $suggestions['quality_tips'][] = [
                'type' => 'reason_quality',
                'message' => 'Try to be more specific in your reasons',
                'example' => 'Instead of "delay", use "waiting for concrete to arrive"',
            ];
        }

        // Behavioral hints based on patterns
        if (isset($userPatterns['category_distribution']['VALID_OPERATIONAL']) && 
            $userPatterns['category_distribution']['VALID_OPERATIONAL'] > 3) {
            $suggestions['behavioral_hints'][] = [
                'type' => 'operational_efficiency',
                'message' => 'Consider planning ahead to reduce operational delays',
                'impact' => 'Could improve overall project efficiency',
            ];
        }

        return $suggestions;
    }
}
