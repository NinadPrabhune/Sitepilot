<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$statChk = ['found' => 0];
$httpMethods = ['get','post','put','patch','delete'];

foreach (array_keys($y['paths'] ?? []) as $pathKey) {
    $ops      = $y['paths'][$pathKey] ?? [];
    $httpKeys = array_values(array_intersect($httpMethods, array_keys($ops)));
    if (empty($httpKeys)) continue;

    foreach ($httpKeys as $m) {
        if (!isset($ops[$m]['requestBody'])) continue;
        $rb =& $y['paths'][$pathKey][$m]['requestBody'];
        if (!isset($rb['content'])) continue;

        foreach ($rb['content'] as $ct => &$ctd) {
            if (!isset($ctd['schema']) || !is_array($ctd['schema'])) continue;
            // This is the exact call from the fixer
            walkSchemaRef($ctd['schema'], $statChk);
        }
    }
}
echo "Total empty_obj_fixed: {$statChk['found']}\n";
echo "approved_dates adl: " . var_export($y['paths']['/api/Hrm/leaves/change-status']['post']['requestBody']['content']['application/json']['schema']['properties']['approved_dates']['additionalProperties'] ?? 'n/a', true) . "\n";

function walkSchemaRef(array &$sch, array &$stats): void
{
    $type = $sch['type'] ?? '';
    if ($type === 'object') {
        $hasProps = !empty($sch['properties']);
        $hasAddl  = isset($sch['additionalProperties']);
        if (!$hasProps && !$hasAddl) {
            echo "FIXING empty obj at " . ($sch['description'] ?? '?') . "\n";
            $sch['additionalProperties'] = true;
            $stats['empty_obj_fixed']++;
        }
    }
    foreach (array_keys($sch) as $k) {
        if (is_array($sch[$k]) && isset($sch[$k]['type'])) {
            walkSchemaRef($sch[$k], $stats);
        }
    }
}
