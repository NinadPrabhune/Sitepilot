<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml', Yaml::PARSE_CONSTANT);

// Categorise paths by HTTP methods patterns
$patterns = array();
foreach ($y['paths'] as $p => $ops) {
    $methods = array_keys($ops);
    $nonOp = array_diff($methods, array('get','post','put','patch','delete','parameters'));
    if ($nonOp) {
        echo "EXTRA KEYS: $p -> " . implode(', ', $nonOp) . PHP_EOL;
    }
    foreach (array('get','post','put','patch','delete') as $m) {
        if (!isset($ops[$m])) continue;
        $o = $ops[$m];
        $key = ($m === 'get' || $m === 'delete') ? 'noRequestBodyOK' : 'hasRB=' . (isset($o['requestBody']) ? '1' : '0');
        if (!array_key_exists($m, $patterns)) $patterns[$m] = array('keys' => array());
        if (!in_array($key, $patterns[$m]['keys'])) {
            $patterns[$m]['keys'][] = $key;
        }
    }
}
echo PHP_EOL . "=== METHOD PATTERN COUNTS ===" . PHP_EOL;
foreach ($patterns as $m => $d) {
    echo "$m: keys=" . implode(',', $d['keys']) . PHP_EOL;
}

// Check a specific path param case
echo PHP_EOL . "=== SAMPLE: /api/Hrm/leaves/{id} ===" . PHP_EOL;
$path = (array_key_exists('/api/Hrm/leaves/{id}', $y['paths'])) ? $y['paths']['/api/Hrm/leaves/{id}'] : null;
if ($path) {
    echo "top-level keys: " . implode(', ', array_keys($path)) . PHP_EOL;
    if (isset($path['parameters'])) {
        echo "path-level params: " . count($path['parameters']) . PHP_EOL;
        foreach ($path['parameters'] as $p2) {
            echo "  - {$p2['name']} in={$p2['in']} required=" . var_export($p2['required'] ?? null, true) . " type=" . ($p2['schema']['type'] ?? 'none') . PHP_EOL;
        }
    }
    foreach (array('get','put','delete') as $m) {
        if (isset($path[$m])) {
            echo "$m params: " . count($path[$m]['parameters'] ?? array()) . PHP_EOL;
        }
    }
}

// Check change-status path
echo PHP_EOL . "=== SAMPLE: /api/Hrm/leaves/change-status ===" . PHP_EOL;
$cs = (array_key_exists('/api/Hrm/leaves/change-status', $y['paths'])) ? $y['paths']['/api/Hrm/leaves/change-status'] : null;
if ($cs) {
    echo "keys: " . implode(', ', array_keys($cs)) . PHP_EOL;
    $post = $cs['post'] ?? array();
    echo "requestBody required: " . var_export(isset($post['requestBody']) ? $post['requestBody']['required'] : 'n/a', true) . PHP_EOL;
    if (isset($post['requestBody']['content'])) {
        echo "requestBody content types: " . implode(', ', array_keys($post['requestBody']['content'])) . PHP_EOL;
        $sch = $post['requestBody']['content']['application/json']['schema'] ?? array();
        echo "schema type: " . var_export(isset($sch['type']) ? $sch['type'] : 'n/a', true) . PHP_EOL;
        echo "properties: " . implode(', ', array_keys($sch['properties'] ?? array())) . PHP_EOL;
        if (isset($sch['properties']['approved_dates'])) {
            $ad = $sch['properties']['approved_dates'];
            echo "approved_dates type: " . var_export(isset($ad['type']) ? $ad['type'] : 'n/a', true) . PHP_EOL;
            echo "approved_dates properties: " . count($ad['properties'] ?? array()) . PHP_EOL;
            if (empty($ad['properties'])) echo "  -> EMPTY OBJECT!" . PHP_EOL;
            echo "approved_dates additionalProperties: " . var_export($ad['additionalProperties'] ?? 'n/a', true) . PHP_EOL;
        }
    }
}

echo PHP_EOL . "=== PATHS W/ path-vars USE WHICH level for params? ===" . PHP_EOL;
$atPath = 0; $atOp = 0; $neither = 0; $matched = 0;
foreach ($y['paths'] as $p => $ops) {
    $hasVar = (bool)preg_match('/\{[^}]+\}/', $p);
    if (!$hasVar) continue;
    $np = (isset($ops['parameters']) && is_array($ops['parameters'])) ? count($ops['parameters']) : 0;
    foreach (array('get','post','put','patch','delete') as $m) {
        if (!isset($ops[$m])) continue;
        $nop = (!empty($ops[$m]['parameters'])) ? count($ops[$m]['parameters']) : 0;
        if ($np > 0 && $nop > 0) { $matched++; }
        elseif ($np > 0) { $atPath++; }
        elseif ($nop > 0) { $atOp++; }
        else { $neither++; }
    }
}
echo "Both levels have params: $matched" . PHP_EOL;
echo "Only path-level: $atPath" . PHP_EOL;
echo "Only op-level: $atOp" . PHP_EOL;
echo "Neither (no path params at all): $neither" . PHP_EOL;

echo PHP_EOL . "=== requestBody missing required=true ===" . PHP_EOL;
$rbNotRequired = 0;
foreach ($y['paths'] as $p => $ops) {
    foreach (array('get','post','put','patch','delete') as $m) {
        if (!isset($ops[$m])) continue;
        if (!isset($ops[$m]['requestBody'])) continue;
        $rb = $ops[$m]['requestBody'];
        $req = isset($rb['required']) ? $rb['required'] : false;
        if (!$req) { echo "$p $m: requestBody required=$req" . PHP_EOL; $rbNotRequired++; }
    }
}
echo "Total: $rbNotRequired" . PHP_EOL;
