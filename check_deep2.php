<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y          = Yaml::parseFile('uploads/scribe/openapi.yaml');
$statChk    = ['found' => 0];
$httpMethods = ['get','post','put','patch','delete'];

$cnt = 0;

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
            $type = $sch['type'] ?? '';
            $cnt++;

            if ($pathKey === '/api/Hrm/leaves/change-status') {
                echo "approved_dates walk: type=" . var_export($type, true) . "\n";
                echo "  === binary: " . bin2hex($type) . "\n";
                echo "  === length: " . strlen($type) . "\n";
                echo "  === === binary comparison:\n";
                var_dump($type === 'object');
                var_dump(strcmp($type, 'object') === 0);
                if (isset($sch['properties']['approved_dates'])) {
                    $ad = $sch['properties']['approved_dates'];
                    echo "  AD type: " . var_export($ad['type'], true) . "\n";
                    echo "  AD type === obj: " . var_export($ad['type'] === 'object', true) . "\n";
                }
            }

            if ($type === 'object' && empty($sch['properties']) && !isset($sch['additionalProperties'])) {
                echo "EMPTY: $pathKey $m $ct\n";
                $statChk['found']++;
            }

            foreach (array_keys($sch) as $k) {
                if (is_array($sch[$k]) && isset($sch[$k]['type'])) {
                    $st = $sch[$k]['type'];
                    if ($st === 'object' && empty($sch[$k]['properties']) && !isset($sch[$k]['additionalProperties'])) {
                        echo "NESTED EMPTY: $pathKey $m $ct -> $k\n";
                        $statChk['found']++;
                    }
                }
            }
        }
    }
}
echo "Total schemas: $cnt\n";
echo "Total empty: {$statChk['found']}\n";
