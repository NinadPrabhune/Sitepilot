<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$cs = $y['paths']['/api/Hrm/leaves/change-status']['post'];
$ad = $cs['requestBody']['content']['application/json']['schema']['properties']['approved_dates'];
echo json_encode($ad, JSON_PRETTY_PRINT) . PHP_EOL;
echo "hasProperties: " . var_export(!empty($ad['properties']), true) . PHP_EOL;
echo "hasAdditionalProperties: " . var_export(isset($ad['additionalProperties']), true) . PHP_EOL;

echo PHP_EOL . "=== Empty objects in the generated YAML ===" . PHP_EOL;
$allYaml = file_get_contents('uploads/scribe/openapi.yaml');
$count = 0;
preg_match_all('/properties:\s*\{\s*\}/', $allYaml, $matches);
echo "Empty properties: " . count($matches[0]) . PHP_EOL;
foreach ($matches[0] as $m) {
    $pos = strpos($allYaml, $m);
    $context = substr($allYaml, max(0, $pos-200), 400);
    if ($pos !== false && $count < 5) {
        echo "Context #$count: " . substr($context, 0, 200) . "...\n";
        $count++;
    }
}
