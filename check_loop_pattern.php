<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y          = Yaml::parseFile('uploads/scribe/openapi.yaml');
$stats      = ['empty_obj_fixed' => 0];
$httpMethods = ['get','post','put','patch','delete'];

// Simulate the EXACT loop pattern from the fixer
foreach (array_keys($y['paths'] ?? []) as $pathKey) {
    $ops      = $y['paths'][$pathKey] ?? [];
    $httpKeys = array_values(array_intersect($httpMethods, array_keys($ops)));
    if (empty($httpKeys)) continue;

    foreach ($httpKeys as $m) {
        if (!isset($ops[$m]['requestBody'])) continue;
        $rb = &$y['paths'][$pathKey][$m]['requestBody'];
        if (!isset($rb['content'])) continue;

        foreach ($rb['content'] as $ct => &$ctd) {
            if (!isset($ctd['schema']) || !is_array($ctd['schema'])) continue;
            echo "Checking $pathKey $m $ct\n";
            $sch = $ctd['schema'];
            $type = $sch['type'] ?? '';
            echo "  type=$type props=" . count($sch['properties'] ?? []) . "\n";
            if ($type === 'object' && empty($sch['properties']) && !isset($sch['additionalProperties'])) {
                echo "  >>> WOULD FIX HERE <<<\n";
            }
        }
    }
}
echo "Stats: " . var_export($stats, true) . "\n";
