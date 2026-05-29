<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y          = Yaml::parseFile('uploads/scribe/openapi.yaml');
$statChk    = ['found' => 0];
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
            $sch = $ctd['schema'];

            // Find approved_dates specifically
            $props = $sch['properties'] ?? [];
            if (isset($props['approved_dates'])) {
                echo "FOUND approved_dates in $pathKey $m $ct\n";
                $ad = $props['approved_dates'];
                echo "  type: " . var_export($ad['type'] ?? 'n/a', true) . "\n";
                echo "  props type: " . gettype($ad['properties'] ?? 'n/a') . "\n";
                echo "  props empty: " . var_export(empty($ad['properties']), true) . "\n";
                echo "  is_array: " . var_export(is_array($ad['properties']), true) . "\n";
                echo "  count: " . count($ad['properties'] ?? []) . "\n";
                echo "  addl: " . var_export(isset($ad['additionalProperties']), true) . "\n";
            }

            // Walk the schema
            $type = $sch['type'] ?? '';
            if ($type === 'object' && empty($sch['properties']) && !isset($sch['additionalProperties'])) {
                echo "EMPTY OBJ SCHEMA: $pathKey $m $ct\n";
                $statChk['found']++;
            }
            // Also check nested
            foreach (array_keys($sch) as $k) {
                if (is_array($sch[$k]) && isset($sch[$k]['type']) && $sch[$k]['type'] === 'object' && empty($sch[$k]['properties']) && !isset($sch[$k]['additionalProperties'])) {
                    echo "NESTED EMPTY OBJ: $pathKey $m $ct -> $k\n";
                    $statChk['found']++;
                }
            }
        }
    }
}
echo "Total empty bodies found (my manual check): {$statChk['found']}\n";
