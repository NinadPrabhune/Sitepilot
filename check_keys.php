<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$pathKey = '/api/Hrm/leaves/change-status';

echo "pathKey exists: " . var_export(isset($y['paths'][$pathKey]), true) . "\n";
if (isset($y['paths'][$pathKey])) {
    $ops = $y['paths'][$pathKey];
    echo "ops keys: " . implode(', ', array_keys($ops)) . "\n";
    echo "has post: " . var_export(isset($ops['post']), true) . "\n";

    echo "post keys: " . implode(', ', array_keys($ops['post'] ?? [])) . "\n";
    echo "has requestBody: " . var_export(isset($ops['post']['requestBody']), true) . "\n";

    if (isset($ops['post']['requestBody'])) {
        $sch = $ops['post']['requestBody']['content']['application/json']['schema'];
        echo "schema type: " . $sch['type'] . "\n";
        echo "has properties: " . var_export(isset($sch['properties']), true) . "\n";
        echo "properties count: " . count($sch['properties'] ?? []) . "\n";
        foreach (array_keys($sch['properties'] ?? []) as $k) echo "  - $k\n";
    }
}
