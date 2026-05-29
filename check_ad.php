<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
foreach (['/api/Hrm/leaves/change-status', '/Hrm/leaves/change-status'] as $pp) {
    $ad = $y['paths'][$pp]['post']['requestBody']['content']['application/json']['schema']['properties']['approved_dates'];
    echo "$pp:\n";
    echo "  properties count: " . count($ad['properties'] ?? []) . "\n";
    echo "  additionalProperties: " . var_export($ad['additionalProperties'] ?? 'n/a', true) . "\n";
}
echo PHP_EOL;

// Show what walkSchema does to it
$test = ['type' => 'object', 'description' => 'test', 'properties' => [], 'example' => []];
function walkSchema2(array &$sch, &$stats): void
{
    $type = $sch['type'] ?? '';
    if ($type === 'object') {
        $hasProps = !empty($sch['properties']);
        $hasAddl  = isset($sch['additionalProperties']);
        if (!$hasProps && !$hasAddl) {
            echo "  FIXED empty object\n";
            $sch['additionalProperties'] = true;
            $stats['empty_obj_fixed']++;
        } else {
            echo "  NOT fixing: hasProps=" . var_export($hasProps, true) . " hasAddl=" . var_export($hasAddl, true) . "\n";
        }
    }
    foreach (array_keys($sch) as $k) {
        if (is_array($sch[$k]) && isset($sch[$k]['type'])) {
            walkSchema2($sch[$k], $stats);
        }
    }
}
echo "walkSchema2 on approved_dates:\n";
$stats2 = [];
walkSchema2($test, $stats2);
