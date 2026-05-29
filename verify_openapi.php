<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi_fixed.yaml', Yaml::PARSE_CONSTANT);

$pass = 0; $fail = 0;
function ok($label, $cond) {
    global $pass, $fail;
    if ($cond) { echo "[OK] $label\n"; $pass++; }
    else        { echo "[FAIL] $label\n"; $fail++; }
}

echo "=== 1. No path-level 'parameters' remain ===" . PHP_EOL;
$stillAtPath = 0;
foreach ($y['paths'] as $ops) {
    if (!empty($ops['parameters'])) $stillAtPath++;
}
ok("no path-item parameters", $stillAtPath === 0);

echo PHP_EOL . "=== 2. Path-var endpoints have params at op level ===" . PHP_EOL;
$missingPP = 0;
foreach ($y['paths'] as $p => $ops) {
    $hasVar = preg_match('/\{[^}]+\}/', $p);
    if (!$hasVar) continue;
    foreach (array('get','post','put','patch','delete') as $m) {
        if (!isset($ops[$m])) continue;
        if (empty($ops[$m]['parameters'])) { $missingPP++; }
    }
}
ok("all var-paths have op-level params", $missingPP === 0);

echo PHP_EOL . "=== 3. requestBody required=true ===" . PHP_EOL;
$rbNotReq = 0;
foreach ($y['paths'] as $p => $ops) {
    foreach (array('post','put','patch') as $m) {
        if (!isset($ops[$m])) continue;
        if (!isset($ops[$m]['requestBody'])) continue;
        $rb = $ops[$m]['requestBody'];
        if (empty($rb['required'])) { $rbNotReq++; echo "  not required: $p $m\n"; }
    }
}
ok("all requestBodies required=true", $rbNotReq === 0);

echo PHP_EOL . "=== 4. Empty object schemas in requestBody ===" . PHP_EOL;
$emptyObj = 0;
foreach ($y['paths'] as $p => $ops) {
    foreach (array('post','put','patch') as $m) {
        if (!isset($ops[$m])) continue;
        if (!isset($ops[$m]['requestBody'])) continue;
        $rb = $ops[$m]['requestBody'];
        if (!isset($rb['content'])) continue;
        foreach ($rb['content'] as $ct => $ctd) {
            $sch = $ctd['schema'] ?? array();
            if ($sch['type'] === 'object') {
                $hasProps   = !empty($sch['properties']);
                $hasAddl    = isset($sch['additionalProperties']);
                $emptyOk    = $hasAddl && !$hasProps;  // open-ended object
                if (!$hasProps && !$hasAddl) {
                    $emptyObj++; echo "  empty object: $p $m $ct\n";
                }
            }
        }
    }
}
ok("no empty/bare object schemas", $emptyObj === 0);

echo PHP_EOL . "=== 5. Spot check: /api/Hrm/leaves/change-status ===" . PHP_EOL;
$cs = $y['paths']['/api/Hrm/leaves/change-status']['post'];
echo "  params  : " . (empty($cs['parameters']) ? "0" : count($cs['parameters'])) . PHP_EOL;
$rb = $cs['requestBody'];
echo "  rb.required : " . var_export($rb['required'], true) . PHP_EOL;
echo "  content types: " . implode(', ', array_keys($rb['content'])) . PHP_EOL;
$sch = $rb['content']['application/json']['schema'];
echo "  type   : " . ($sch['type'] ?? 'n/a') . PHP_EOL;
$props = array_keys($sch['properties'] ?? array());
echo "  properties: " . implode(', ', $props) . PHP_EOL;
ok("leave_id present", in_array('leave_id', $props));
ok("status present", in_array('status', $props));
ok("status_reason present", in_array('status_reason', $props));
ok("approved_dates present", in_array('approved_dates', $props));
ok("approved_days present", in_array('approved_days', $props));
if (isset($sch['properties']['approved_dates']['type'])) {
    echo "  approved_dates type: " . $sch['properties']['approved_dates']['type'] . PHP_EOL;
    echo "  approved_dates additionalProperties: " . var_export($sch['properties']['approved_dates']['additionalProperties'] ?? 'n/a', true) . PHP_EOL;
}
ok("approved_dates type=object", ($sch['properties']['approved_dates']['type'] ?? '') === 'object');

echo PHP_EOL . "=== 6. Total stats ===" . PHP_EOL;
echo "Paths: " . count($y['paths']) . PHP_EOL;
$opCount = 0;
foreach ($y['paths'] as $ops) {
    foreach (array('get','post','put','patch','delete') as $m) {
        if (isset($ops[$m])) $opCount++;
    }
}
echo "Operations: $opCount" . PHP_EOL;
echo PHP_EOL . "TOTALS: pass=$pass fail=$fail" . PHP_EOL;
