<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$pathKey = '/api/Hrm/leaves/change-status';
echo "exists: " . var_export(isset($y['paths'][$pathKey]), true) . "\n";
if (isset($y['paths'][$pathKey])) {
    $ops = $y['paths'][$pathKey];
    echo "keys: " . implode(', ', array_keys($ops)) . "\n";
    if (isset($ops['post'])) {
        echo "post has requestBody: " . var_export(isset($ops['post']['requestBody']), true) . "\n";
        $rb = $ops['post']['requestBody'];
        echo "required: " . var_export($rb['required'] ?? 'n/a', true) . "\n";
        if (isset($rb['content']['application/json']['schema']['properties']['approved_dates'])) {
            $ad = $rb['content']['application/json']['schema']['properties']['approved_dates'];
            echo "ad type: " . $ad['type'] . "\n";
            echo "ad has props: " . var_export(isset($ad['properties']), true) . "\n";
            echo "ad props empty: " . var_export(empty($ad['properties']), true) . "\n";
        }
    }
}
