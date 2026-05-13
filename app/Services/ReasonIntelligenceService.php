<?php

namespace App\Services;

/**
 * Reason Intelligence Service
 * Categorizes and learns from override reasons to provide decision intelligence
 */
class ReasonIntelligenceService
{
    /**
     * Reason categories with intelligence classification
     */
    private const REASON_CATEGORIES = [
        'VALID_OPERATIONAL' => [
            'keywords' => ['rain', 'weather', 'delay', 'waiting', 'materials', 'traffic', 'break', 'shift', 'changeover'],
            'patterns' => ['/rain/i', '/weather/i', '/delay/i', '/wait/i', '/material/i'],
            'weight' => 0.9,
            'description' => 'Legitimate operational reasons',
        ],
        'VALID_TECHNICAL' => [
            'keywords' => ['breakdown', 'maintenance', 'repair', 'fault', 'issue', 'problem', 'malfunction'],
            'patterns' => ['/breakdown/i', '/maintenance/i', '/repair/i', '/fault/i'],
            'weight' => 0.8,
            'description' => 'Technical equipment issues',
        ],
        'VALID_BUSINESS' => [
            'keywords' => ['client', 'requirement', 'specification', 'change', 'request', 'approval', 'urgent'],
            'patterns' => ['/client/i', '/requirement/i', '/urgent/i', '/approval/i'],
            'weight' => 0.7,
            'description' => 'Business-driven changes',
        ],
        'SUSPICIOUS' => [
            'keywords' => ['adjusted', 'modified', 'changed', 'fixed', 'corrected', 'updated'],
            'patterns' => ['/adjust/i', '/modify/i', '/fix/i', '/correct/i'],
            'weight' => 0.3,
            'description' => 'Potentially suspicious reasons',
        ],
        'LOW_QUALITY' => [
            'keywords' => ['ok', 'fine', 'proceed', 'continue', 'yes', 'confirmed', 'correct', 'done'],
            'patterns' => ['/^ok$/i', '/^fine$/i', '/^yes$/i', '/^confirmed$/i'],
            'weight' => 0.1,
            'description' => 'Low-quality or generic reasons',
        ],
        'UNKNOWN' => [
            'keywords' => [],
            'patterns' => [],
            'weight' => 0.5,
            'description' => 'Uncategorized reasons',
        ],
    ];

    /**
     * Categorize override reason with intelligence
     */
    public static function categorizeReason(string $reason): array
    {
        $reason = trim(strtolower($reason));
        $category = 'UNKNOWN';
        $confidence = 0.5;
        $analysis = [];

        // Check each category
        foreach (self::REASON_CATEGORIES as $catName => $catData) {
            $score = self::calculateCategoryScore($reason, $catData);
            
            if ($score > $confidence) {
                $category = $catName;
                $confidence = $score;
                $analysis = [
                    'matched_keywords' => self::getMatchedKeywords($reason, $catData['keywords']),
                    'matched_patterns' => self::getMatchedPatterns($reason, $catData['patterns']),
                    'weight' => $catData['weight'],
                ];
            }
        }

        return [
            'category' => $category,
            'confidence' => $confidence,
            'weight' => self::REASON_CATEGORIES[$category]['weight'],
            'description' => self::REASON_CATEGORIES[$category]['description'],
            'analysis' => $analysis,
            'is_legitimate' => $confidence > 0.7 && self::REASON_CATEGORIES[$category]['weight'] > 0.6,
        ];
    }

    /**
     * Calculate category score for reason
     */
    private static function calculateCategoryScore(string $reason, array $categoryData): float
    {
        $score = 0.0;
        
        // Keyword matching
        $keywordMatches = self::getMatchedKeywords($reason, $categoryData['keywords']);
        if (!empty($categoryData['keywords'])) {
            $keywordScore = count($keywordMatches) / count($categoryData['keywords']);
            $score += $keywordScore * 0.6;
        }
        
        // Pattern matching
        $patternMatches = self::getMatchedPatterns($reason, $categoryData['patterns']);
        if (!empty($categoryData['patterns'])) {
            $patternScore = count($patternMatches) / count($categoryData['patterns']);
            $score += $patternScore * 0.4;
        }
        
        return $score * $categoryData['weight'];
    }

    /**
     * Get matched keywords
     */
    private static function getMatchedKeywords(string $reason, array $keywords): array
    {
        $matched = [];
        foreach ($keywords as $keyword) {
            if (strpos($reason, $keyword) !== false) {
                $matched[] = $keyword;
            }
        }
        return $matched;
    }

    /**
     * Get matched patterns
     */
    private static function getMatchedPatterns(string $reason, array $patterns): array
    {
        $matched = [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $reason)) {
                $matched[] = $pattern;
            }
        }
        return $matched;
    }

    /**
     * Analyze user reason patterns over time
     */
    public static function analyzeUserReasonPatterns(int $userId, string $period = '30_days'): array
    {
        $dateRange = self::getDateRange($period);
        
        // Get user's override reasons
        $reasons = \Illuminate\Support\Facades\DB::table('warning_overrides')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $dateRange)
            ->pluck('reason')
            ->toArray();

        $analysis = [
            'total_reasons' => count($reasons),
            'category_distribution' => [],
            'quality_trend' => [],
            'legitimate_rate' => 0,
            'suspicious_patterns' => [],
            'recommendations' => [],
        ];

        if (empty($reasons)) {
            return $analysis;
        }

        // Categorize all reasons
        $categorizedReasons = [];
        foreach ($reasons as $reason) {
            $categorizedReasons[] = self::categorizeReason($reason);
        }

        // Calculate distribution
        foreach ($categorizedReasons as $categorized) {
            $cat = $categorized['category'];
            $analysis['category_distribution'][$cat] = ($analysis['category_distribution'][$cat] ?? 0) + 1;
        }

        // Calculate legitimate rate
        $legitimateCount = count(array_filter($categorizedReasons, fn($r) => $r['is_legitimate']));
        $analysis['legitimate_rate'] = round(($legitimateCount / count($categorizedReasons)) * 100, 2);

        // Identify suspicious patterns
        $suspiciousCount = $analysis['category_distribution']['SUSPICIOUS'] ?? 0;
        $lowQualityCount = $analysis['category_distribution']['LOW_QUALITY'] ?? 0;
        
        if ($suspiciousCount > 2) {
            $analysis['suspicious_patterns'][] = 'High frequency of suspicious reasons';
        }
        
        if ($lowQualityCount > 3) {
            $analysis['suspicious_patterns'][] = 'High frequency of low-quality reasons';
        }

        // Generate recommendations
        $analysis['recommendations'] = self::generateReasonRecommendations($analysis);

        return $analysis;
    }

    /**
     * Generate recommendations based on reason analysis
     */
    private static function generateReasonRecommendations(array $analysis): array
    {
        $recommendations = [];

        if ($analysis['legitimate_rate'] < 60) {
            $recommendations[] = [
                'type' => 'training',
                'priority' => 'high',
                'message' => 'Low legitimate reason rate (' . $analysis['legitimate_rate'] . '%)',
                'suggestion' => 'Provide training on proper reason documentation',
            ];
        }

        if (isset($analysis['category_distribution']['LOW_QUALITY']) && $analysis['category_distribution']['LOW_QUALITY'] > 2) {
            $recommendations[] = [
                'type' => 'process',
                'priority' => 'medium',
                'message' => 'Multiple low-quality reasons detected',
                'suggestion' => 'Enforce minimum reason quality standards',
            ];
        }

        if (isset($analysis['category_distribution']['VALID_OPERATIONAL']) && 
            $analysis['category_distribution']['VALID_OPERATIONAL'] > 5) {
            $recommendations[] = [
                'type' => 'operational',
                'priority' => 'low',
                'message' => 'Frequent operational delays',
                'suggestion' => 'Review operational processes to reduce delays',
            ];
        }

        return $recommendations;
    }

    /**
     * Get team reason intelligence
     */
    public static function getTeamReasonIntelligence(array $userIds, string $period = '30_days'): array
    {
        $teamAnalysis = [
            'total_users' => count($userIds),
            'overall_legitimate_rate' => 0,
            'category_distribution' => [],
            'top_reasons' => [],
            'user_quality_ranking' => [],
            'team_insights' => [],
        ];

        $allCategorizedReasons = [];
        $userLegitimateRates = [];

        foreach ($userIds as $userId) {
            $userAnalysis = self::analyzeUserReasonPatterns($userId, $period);
            
            // Store legitimate rate
            $userLegitimateRates[$userId] = $userAnalysis['legitimate_rate'];
            
            // Collect all categorized reasons
            $dateRange = self::getDateRange($period);
            $reasons = \Illuminate\Support\Facades\DB::table('warning_overrides')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $dateRange)
                ->pluck('reason')
                ->toArray();

            foreach ($reasons as $reason) {
                $categorized = self::categorizeReason($reason);
                $allCategorizedReasons[] = $categorized;
            }
        }

        // Calculate overall legitimate rate
        if (!empty($userLegitimateRates)) {
            $teamAnalysis['overall_legitimate_rate'] = round(array_sum($userLegitimateRates) / count($userLegitimateRates), 2);
        }

        // Calculate team category distribution
        foreach ($allCategorizedReasons as $categorized) {
            $cat = $categorized['category'];
            $teamAnalysis['category_distribution'][$cat] = ($teamAnalysis['category_distribution'][$cat] ?? 0) + 1;
        }

        // Get top reasons
        $allReasons = \Illuminate\Support\Facades\DB::table('warning_overrides')
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', self::getDateRange($period))
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        foreach ($allReasons as $reasonData) {
            $categorized = self::categorizeReason($reasonData->reason);
            $teamAnalysis['top_reasons'][] = [
                'reason' => $reasonData->reason,
                'count' => $reasonData->count,
                'category' => $categorized['category'],
                'legitimate' => $categorized['is_legitimate'],
            ];
        }

        // Rank users by quality
        arsort($userLegitimateRates);
        $teamAnalysis['user_quality_ranking'] = $userLegitimateRates;

        // Generate team insights
        $teamAnalysis['team_insights'] = self::generateTeamInsights($teamAnalysis);

        return $teamAnalysis;
    }

    /**
     * Generate team insights
     */
    private static function generateTeamInsights(array $teamAnalysis): array
    {
        $insights = [];

        if ($teamAnalysis['overall_legitimate_rate'] < 70) {
            $insights[] = [
                'type' => 'quality',
                'message' => 'Team legitimate reason rate is low (' . $teamAnalysis['overall_legitimate_rate'] . '%)',
                'impact' => 'May indicate need for team training',
            ];
        }

        $suspiciousCount = $teamAnalysis['category_distribution']['SUSPICIOUS'] ?? 0;
        $totalReasons = array_sum($teamAnalysis['category_distribution']);
        
        if ($totalReasons > 0 && ($suspiciousCount / $totalReasons) > 0.2) {
            $insights[] = [
                'type' => 'risk',
                'message' => 'High proportion of suspicious reasons (' . round(($suspiciousCount / $totalReasons) * 100, 1) . '%)',
                'impact' => 'Requires management attention',
            ];
        }

        if (isset($teamAnalysis['category_distribution']['VALID_OPERATIONAL']) && 
            $teamAnalysis['category_distribution']['VALID_OPERATIONAL'] > 10) {
            $insights[] = [
                'type' => 'operational',
                'message' => 'Frequent operational issues across team',
                'impact' => 'May indicate process or resource problems',
            ];
        }

        return $insights;
    }

    /**
     * Get reason suggestions for user
     */
    public static function getReasonSuggestions(string $warningType, int $userId = null): array
    {
        $suggestions = self::getBaseReasonSuggestions($warningType);

        // If user provided, personalize based on their patterns
        if ($userId) {
            $userPatterns = self::analyzeUserReasonPatterns($userId, '90_days');
            
            // Boost suggestions that match user's legitimate patterns
            if ($userPatterns['legitimate_rate'] > 70) {
                $suggestions = self::personalizeSuggestions($suggestions, $userPatterns);
            }
        }

        return $suggestions;
    }

    /**
     * Get base reason suggestions for warning type
     */
    private static function getBaseReasonSuggestions(string $warningType): array
    {
        $suggestions = match($warningType) {
            'excessive_idle_hours' => [
                ['text' => 'Machine idle due to weather conditions', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Waiting for materials to arrive on site', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Equipment maintenance during operation', 'category' => 'VALID_TECHNICAL'],
                ['text' => 'Shift changeover and crew transition', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Power outage or utility interruption', 'category' => 'VALID_OPERATIONAL'],
            ],
            'operator_mismatch' => [
                ['text' => 'Operator names not available at time of entry', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Temporary operator assigned for this shift', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Names will be updated in next shift', 'category' => 'VALID_BUSINESS'],
                ['text' => 'Multiple operators shared duties', 'category' => 'VALID_OPERATIONAL'],
            ],
            'duplicate_diesel' => [
                ['text' => 'Additional fuel required due to extended work hours', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Previous entry was incorrect - correction needed', 'category' => 'VALID_TECHNICAL'],
                ['text' => 'Multiple fuel supplies used during shift', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Fuel consumption split across different work periods', 'category' => 'VALID_OPERATIONAL'],
            ],
            'diesel_without_dpr' => [
                ['text' => 'DPR will be created later in the system', 'category' => 'VALID_BUSINESS'],
                ['text' => 'Fuel entry for maintenance activities', 'category' => 'VALID_TECHNICAL'],
                ['text' => 'Administrative fuel entry for inventory tracking', 'category' => 'VALID_BUSINESS'],
                ['text' => 'Fuel used for equipment testing', 'category' => 'VALID_TECHNICAL'],
            ],
            'high_consumption_rate' => [
                ['text' => 'Heavy load conditions due to terrain', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Extended operating hours with continuous use', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Difficult working conditions requiring more power', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Equipment performance issues affecting efficiency', 'category' => 'VALID_TECHNICAL'],
            ],
            default => [
                ['text' => 'Business requirement justification', 'category' => 'VALID_BUSINESS'],
                ['text' => 'Operational necessity', 'category' => 'VALID_OPERATIONAL'],
                ['text' => 'Technical constraint', 'category' => 'VALID_TECHNICAL'],
                ['text' => 'Process exception approved', 'category' => 'VALID_BUSINESS'],
            ],
        };

        return $suggestions;
    }

    /**
     * Personalize suggestions based on user patterns
     */
    private static function personalizeSuggestions(array $suggestions, array $userPatterns): array
    {
        // Boost categories that user frequently uses legitimately
        $personalized = [];
        
        foreach ($suggestions as $suggestion) {
            $category = $suggestion['category'];
            
            // Check if user has legitimate reasons in this category
            if (isset($userPatterns['category_distribution'][$category]) && 
                $userPatterns['category_distribution'][$category] > 0) {
                $suggestion['personalized'] = true;
                $suggestion['boost'] = 'Based on your previous entries';
            }
            
            $personalized[] = $suggestion;
        }

        // Sort personalized suggestions first
        usort($personalized, function($a, $b) {
            return ($b['personalized'] ?? false) <=> ($a['personalized'] ?? false);
        });

        return $personalized;
    }

    /**
     * Get date range for period
     */
    private static function getDateRange(string $period): string
    {
        return match($period) {
            '7_days' => now()->subDays(7),
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            default => now()->subDays(30),
        };
    }

    /**
     * Validate reason quality before storage
     */
    public static function validateReasonQuality(string $reason): array
    {
        $validation = [
            'valid' => true,
            'score' => 0,
            'issues' => [],
            'suggestions' => [],
        ];

        $categorized = self::categorizeReason($reason);
        $validation['score'] = $categorized['weight'];

        // Check for quality issues
        if ($categorized['category'] === 'LOW_QUALITY') {
            $validation['valid'] = false;
            $validation['issues'][] = 'Reason is too generic or low quality';
            $validation['suggestions'] = self::getReasonSuggestions('general');
        }

        if ($categorized['category'] === 'SUSPICIOUS') {
            $validation['valid'] = false;
            $validation['issues'][] = 'Reason appears suspicious';
            $validation['suggestions'] = self::getReasonSuggestions('general');
        }

        if (strlen(trim($reason)) < 10) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Reason is too short';
        }

        return $validation;
    }
}
