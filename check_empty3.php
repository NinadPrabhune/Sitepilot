<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y     = Yaml::parseFile('uploads/scribe/openapi.yaml');
$stats = ['empty_obj_fixed' => 0];
$found = 0;

function walkSchema3(array &$sch, array &$stats, &$context = ''): void
{
    $context = ($context ? $context . ' > ' : '') . ($sch['type'] ?? 'root');
    $type = $sch['type'] ?? '';

    if ($type === 'object') {
        $hasProps = !empty($sch['properties']);
        $hasAddl  = isset($sch['additionalProperties']);
        if (!$hasProps && !$hasAddl) {
            echo "EMPTY SCHEMA FIXED at: $context\n";
            $sch['additionalProperties'] = true;
            $stats['empty_obj_fixed']++;
            $found++;
        }
    }
    foreach (array_keys($sch) as $k) {
        if (is_array($sch[$k]) && isset($sch[$k]['type'])) {
            walkSchema3($sch[$k], $stats, $context);
        }
    }
}

$httpMethods = ['get','post','put','patch','delete'];
foreach (array_keys($y['paths'] ?? []) as $pathKey) {
    $ops      = $y['paths'][$pathKey] ?? [];
    $httpKeys = array_values(array_intersect($httpMethods, array_keys($ops)));
    if (empty($httpKeys)) continue;

    foreach ($httpKeys as $m) {
        if (!isset($ops[$m]['requestBody'])) continue;
        if (!isset($ops[$m]['requestBody']['content'])) continue;

        foreach ($ops[$m]['requestBody']['content'] as $ct => $ctd) {
            if (!isset($ctd['schema'])) continue;

            // REPLICATE EXACTLY what fix_apidog_openapi does (reference vs copy)
            // NOTE: Here $y['paths'][$pathKey][$m]['requestBody'] is a REFERENCE
            // into the $y array. $ctd is a copy! Changes to $ctd won't affect $y.
            // BUT we walkSchema on a COPY... wait no, fix_apidog passes $ctd['schema']
            // on line: walkSchema($ctd['schema'], $stats)
            // $ctd is a COPY, so $ctd['schema'] is a fresh copy of the schema array.
            // Changes to THAT copy don't affect $y -> STATS ARE WRONG for modifications!
            // BUT we DO pass by-ref:
            // In fix_apidog: walkSchema($ctd['schema'], $stats)  <- not by-ref inside walkSchema
            // Oh! The first argument IS passed by ref in walkSchema, but $ctd['schema']
            // is an array expression so it's a copy... but wait PHP 5.5+ allows array[] by-ref?
            // NO! $ctd['schema'] creates a LOCAL COPY. Modifying it inside walkSchema doesn't
            // modify the original $y array.
            //
            // BUT the stats (second arg) IS passed by reference! So stats SHOULD accumulate.
            //
            // CONCLUSION: the dup + $ctd issue means $y stays unmodified, but $stats still works

            $sch = &$ops[$m]['requestBody']['content'][$ct]['schema'];
            if (!is_array($sch)) continue;
            $ctx = '';
            walkSchema3($sch, $stats, $ctx);
        }
    }
}
echo "Total empty found: {$stats['empty_obj_fixed']}\n";
echo "flattened found: $found\n"; // might be different due to deep recursion
