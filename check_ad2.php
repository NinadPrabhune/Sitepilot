<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');

// Check what we actually get from the parsed YAML
$ad = $y['paths']['/api/Hrm/leaves/change-status']['post']['requestBody']['content']['application/json']['schema']['properties']['approved_dates'];

echo "=== approved_dates raw structure ===\n";
echo "  type type: " . gettype($ad['type']) . " value: " . var_export($ad['type'], true) . "\n";
echo "  properties type: " . gettype($ad['properties']) . " value: " . var_export($ad['properties'], true) . "\n";
echo "  is empty: " . var_export(empty($ad['properties']), true) . "\n";
echo "  count: " . count($ad['properties'] ?? []) . "\n\n";

// Now test walkSchema
$test_copy = $ad; // copy
$stats = ['empty_obj_fixed' => 0];

// Replicate walkSchema
function walkSchema2(array &$sch, &$stats): void
{
    $type = $sch['type'] ?? '';
    echo "  visiting type=$type\n";
    if ($type === 'object') {
        $hasProps = !empty($sch['properties']);
        $hasAddl  = isset($sch['additionalProperties']);
        echo "    hasProps=" . var_export($hasProps, true) . "\n";
        echo "    decision: " . (!$hasProps && !$hasAddl ? "FIX (add additionalProperties)" : "skip") . "\n";
        if (!$hasProps && !$hasAddl) {
            $sch['additionalProperties'] = true;
            $stats['empty_obj_fixed']++;
        }
    }
    foreach (array_keys($sch) as $k) {
        if (is_array($sch[$k]) && isset($sch[$k]['type'])) {
            echo "    recursing into $k\n";
            walkSchema2($sch[$k], $stats);
        }
    }
}

echo "=== walkSchema2 on approved_dates ===\n";
walkSchema2($test_copy, $stats);
echo "Stats: " . var_export($stats, true) . "\n";
