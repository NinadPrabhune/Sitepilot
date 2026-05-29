<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$pathKey = '/api/Hrm/leaves/change-status';
$m       = 'post';

$rb = $y['paths'][$pathKey][$m]['requestBody'];
echo "requestBody type: " . gettype($rb) . "\n";
echo "requestBody keys: " . implode(', ', array_keys($rb)) . "\n";
echo "required: " . var_export($rb['required'] ?? 'n/a', true) . "\n\n";

echo "content keys: " . implode(', ', array_keys($rb['content'] ?? [])) . "\n\n";

$sch = $rb['content']['application/json']['schema'] ?? null;
echo "schema type: " . gettype($sch) . "\n";
echo "schema keys: " . implode(', ', array_keys($sch ?? [])) . "\n\n";

$properties = $sch['properties'] ?? [];
echo "approved_dates type: " . gettype($properties['approved_dates'] ?? 'n/a') . "\n";
$ad = $properties['approved_dates'] ?? null;
echo "approved_dates:\n";
echo "  type: " . var_export($ad['type'] ?? 'n/a', true) . "\n";
echo "  properties type: " . gettype($ad['properties'] ?? 'n/a') . "\n";
echo "  properties value: " . var_export($ad['properties'], true) . "\n";
echo "  allowed: " . var_export(isset($ad['additionalProperties']), true) . "\n";
echo "  empty properties: " . var_export(empty($ad['properties']), true) . "\n";
echo "  count: " . count($ad['properties'] ?? []) . "\n\n";

echo "=== Direct walkSchemaRef test ===\n";
$testSchema = ['type' => 'object', 'description' => 'test', 'properties' => [], 'example' => []];
$testStats  = ['empty_obj_fixed' => 0];
walkSchemaRef($testSchema, $testStats);
echo "Result schema: " . json_encode($testSchema) . "\n";
echo "Stats: " . var_export($testStats, true) . "\n\n";

echo "=== Same test on AD copy ===\n";
$adCopy = $y['paths'][$pathKey][$m]['requestBody']['content']['application/json']['schema']['properties']['approved_dates'];
echo "AD copy type: " . gettype($adCopy) . "\n";
echo "AD copy['type']: " . var_export($adCopy['type'], true) . "\n";
$adStats = ['empty_obj_fixed' => 0];
walkSchemaRef($adCopy, $adStats);
echo "AD copy properties type after walk: " . gettype($adCopy['properties']) . "\n";
echo "AD copy additionalProperties: " . var_export($adCopy['additionalProperties'] ?? 'n/a', true) . "\n";
echo "AD stats: " . var_export($adStats, true) . "\n";

echo "=== The REAL issue ===\n";
echo "adCopy['type'] check:\n";
echo '  $adCopy["type"] === "object": ' . var_export(($adCopy['type'] ?? '') === 'object', true) . "\n";
echo '  type is ' . gettype($adCopy['type']) . " value=" . var_export($adCopy['type'], true) . "\n";

// Simulate what happens WITH $y via reference
echo "\n=== Via reference into y ===\n";
$adFromY =& $y['paths'][$pathKey][$m]['requestBody']['content']['application/json']['schema']['properties']['approved_dates'];
echo "Before - additionalProperties: " . var_export($adFromY['additionalProperties'] ?? 'n/a', true) . "\n";
$yStats = ['empty_obj_fixed' => 0];
walkSchemaRef($y['paths'][$pathKey][$m]['requestBody']['content']['application/json']['schema']['properties']['approved_dates'], $yStats);
echo "After  - additionalProperties: " . var_export($adFromY['additionalProperties'] ?? 'n/a', true) . "\n";
echo "Stats changed: " . var_export($yStats, true) . "\n";

function walkSchemaRef(array &$sch, array &$stats): void
{
    $type = $sch['type'] ?? '';
    if ($type === 'object') {
        $hasProps = !empty($sch['properties']);
        $hasAddl  = isset($sch['additionalProperties']);
        if (!$hasProps && !$hasAddl) {
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
