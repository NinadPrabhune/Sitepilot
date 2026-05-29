<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$stats_arr2 = ['empty_obj_fixed' => 0];

// Walk justApproved_dates
$ad =& $y['paths']['/api/Hrm/leaves/change-status']['post']['requestBody']['content']['application/json']['schema']['properties']['approved_dates'];
echo "AD type: " . var_export($ad['type'], true) . "\n";
echo "AD is obj: " . var_export($ad['type'] === 'object', true) . "\n";
echo "AD props count: " . count($ad['properties'] ?? []) . "\n";

walk_debug($ad, $stats_arr2);

echo "AD after: addl=" . var_export($ad['additionalProperties'] ?? 'n/a', true) . "\n";
echo "Stats: " . var_export($stats_arr2, true) . "\n";

function walk_debug(array &$sch, array &$stats)
{
    echo "  walk_debug: type=" . var_export($sch['type'] ?? '-', true) . "\n";
    if ($sch['type'] === 'object') {
        $props = $sch['properties'] ?? [];
        echo "  -> hasProps=" . var_export(!empty($props), true) . " props_count=" . count($props) . "\n";
        echo "  -> hasInitProps=" . var_export(isset($sch['properties']), true) . "\n";
        $ps = print_r($props, true);
        echo "  -> props repr: " . substr($ps, 0, 80) . "\n";

        if (empty($props) && !isset($sch['additionalProperties'])) {
            echo "  -> FIXING!\n";
            $sch['additionalProperties'] = true;
            $stats['empty_obj_fixed']++;
        }
    }
    foreach (array_keys($sch) as $k) {
        if (is_array($sch[$k]) && isset($sch[$k]['type'])) {
            echo "  -> recursing into $k\n";
            walk_debug($sch[$k], $stats);
        }
    }
}
