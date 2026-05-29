<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

// Simulate the fixer's walk through all requestBody schemas
$y     = Yaml::parseFile('uploads/scribe/openapi.yaml');
$stats = ['empty_obj_fixed' => 0];

$httpMethods = ['get', 'post', 'put', 'patch', 'delete'];
$totalChecked = 0;

foreach (array_keys($y['paths'] ?? []) as $pathKey) {
    $ops = $y['paths'][$pathKey] ?? [];
    $httpKeys = array_values(array_intersect($httpMethods, array_keys($ops)));
    if (empty($httpKeys)) continue;

    foreach ($httpKeys as $m) {
        if (!isset($ops[$m]['requestBody'])) continue;
        $rb = $ops[$m]['requestBody'];
        if (!isset($rb['content'])) continue;

        foreach ($rb['content'] as $ct => $ctd) {
            if (!isset($ctd['schema'])) continue;
            $totalChecked++;
            // Inline walkSchema call
            $sch = $ctd['schema'];
            $type = $sch['type'] ?? '';
            if ($type === 'object' && empty($sch['properties']) && !isset($sch['additionalProperties'])) {
                $stats['empty_obj_fixed']++;
                echo "FOUND: $pathKey $m $ct\n";
            }
        }
    }
}
echo "Total requestBody schemas checked: $totalChecked\n";
echo "Empty object schemas found: {$stats['empty_obj_fixed']}\n";
