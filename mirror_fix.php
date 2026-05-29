<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$stats_arr = ['path_params_ops' => 0, 'rb_fixed' => 0, 'empty_obj_fixed' => 0];
$httpMethods = ['get','post','put','patch','delete'];
$extraCount = 0;

function walkSchema_mirror(array &$sch, array &$stats)
{
    if (!is_array($sch) || !isset($sch['type'])) return;
    $type = $sch['type'];
    if ($type === 'object') {
        $hp = !empty($sch['properties']);
        $ha = isset($sch['additionalProperties']);
        if (!$hp && !$ha) {
            $sch['additionalProperties'] = true;
            $stats['empty_obj_fixed']++;
            $GLOBALS['extraCount']++;
        }
    }
    foreach (array_keys($sch) as $k) {
        if (is_array($sch[$k]) && isset($sch[$k]['type'])) {
            walkSchema_mirror($sch[$k], $stats);
        }
    }
}

foreach (array_keys($y['paths'] ?? []) as $pathKey) {
    $ops = $y['paths'][$pathKey] ?? [];
    $httpKeys = array_values(array_intersect($httpMethods, array_keys($ops)));
    if (empty($httpKeys)) continue;

    foreach ($httpKeys as $m) {
        if (!isset($ops[$m]['requestBody'])) continue;
        $op =& $y['paths'][$pathKey][$m];
        $rb =& $op['requestBody'];
        if (!isset($rb['required']) || $rb['required'] !== true) {
            $rb['required'] = true;
            $stats_arr['rb_fixed']++;
        }
        if (!isset($rb['content']) || !is_array($rb['content'])) continue;

        foreach ($rb['content'] as $ct => &$ctd) {
            if (!isset($ctd['schema']) || !is_array($ctd['schema'])) continue;
            walkSchema_mirror($ctd['schema'], $stats_arr);
        }
    }
}

echo "Fixer stats: " . json_encode($stats_arr, JSON_PRETTY_PRINT) . "\n";
echo "Global count: $extraCount\n";
$ad = $y['paths']['/api/Hrm/leaves/change-status']['post']['requestBody']['content']['application/json']['schema']['properties']['approved_dates'];
echo "AD additionalProperties: " . var_export($ad['additionalProperties'] ?? 'n/a', true) . "\n";
