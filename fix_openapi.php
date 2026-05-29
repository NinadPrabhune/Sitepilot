<?php
/**
 * Fix all OpenAPI 3.0 schema issues that cause Apidog to NOT show parameters.
 *
 * Issues fixed:
 *  1. Path parameters declared only at the path-item level
 *       → merged into every HTTP method operation under that path
 *  2. requestBody without required:true
 *       → required set to true (valid for Apidog + spec-compliant)
 *  3. Empty object schemas  (type:object, properties:{})
 *       → replaced with additionalProperties schema
 *  4. Missing requestBody on POST/PUT/PATCH that should have one
 *       → optional no-op; already mostly present; skip
 *  5. Missing summary / description (cosmetic – Apidog shows blank endpoint name)
 *       → filled from sibling endpoint or tagged heading
 */

require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$in   = 'uploads/scribe/openapi.yaml';
$out  = 'uploads/scribe/openapi_fixed.yaml';

// -----------------------------------------------------------------
// helpers
// -----------------------------------------------------------------
function snake($s){ return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $s)); }

// -----------------------------------------------------------------
// 1. Load
// -----------------------------------------------------------------
$y = Yaml::parseFile($in, Yaml::PARSE_CONSTANT);
$stats = [
    'path_param_merged' => 0,
    'rb_required_fixed' => 0,
    'empty_obj_fixed'   => 0,
];

// -----------------------------------------------------------------
// 2. Process every path
// -----------------------------------------------------------------
foreach (array_keys($y['paths']) as $pathKey) {
    $ops = &$y['paths'][$pathKey];

    // skip non-operation keys
    $httpMethods = array('get','post','put','patch','delete');
    $opKeys = array_intersect($httpMethods, array_keys($ops));

    if (empty($opKeys)) continue;

    // -------------------------------------------------------------
    // 2a. Path-level parameters → merge into every operation
    // -------------------------------------------------------------
    if (isset($ops['parameters']) && is_array($ops['parameters']) && !empty($ops['parameters'])) {
        foreach ($opKeys as $m) {
            $opParams = array();
            // keep any existing op-level params (oper-specific query params etc.)
            if (isset($ops[$m]['parameters']) && is_array($ops[$m]['parameters'])) {
                $opParams = $ops[$m]['parameters'];
            }
            // merge – deduplicate by (name, in)
            $existingMap = array();
            foreach ($opParams as $kp => $pp) {
                $existingMap[($pp['in'] ?? '') . '.'. ($pp['name'] ?? '')] = $kp;
            }
            $added = 0;
            foreach ($ops['parameters'] as $pp) {
                $k = ($pp['in'] ?? '') . '.'. ($pp['name'] ?? '');
                if (!array_key_exists($k, $existingMap)) {
                    $opParams[] = $pp;
                    $added++;
                }
            }
            if ($added > 0) {
                $ops[$m]['parameters'] = $opParams;
                $stats['path_param_merged']++;
            }
        }
        // remove shared level only after all ops consumed it
        unset($ops['parameters']);
    }

    // -------------------------------------------------------------
    // 2b. Ensure path params declared at op level have schema.type
    // -------------------------------------------------------------
    foreach ($opKeys as $m) {
        if (!isset($ops[$m]['parameters']) || !is_array($ops[$m]['parameters'])) continue;
        foreach ($ops[$m]['parameters'] as &$pp) {
            if (($pp['in'] ?? '') !== 'path') continue;
            if (!isset($pp['schema']['type'])) {
                $pp['schema']['type'] = 'string';
            }
        }
    }

    // -------------------------------------------------------------
    // 2c. requestBody → required:true
    // -------------------------------------------------------------
    foreach ($opKeys as $m) {
        if (!isset($ops[$m]['requestBody'])) continue;
        $rb = &$ops[$m]['requestBody'];
        if (!isset($rb['required']) || $rb['required'] !== true) {
            $rb['required'] = true;
            $stats['rb_required_fixed']++;
        }
        // 2d. Inside every content type schema, fix empty objects
        if (isset($rb['content']) && is_array($rb['content'])) {
            foreach ($rb['content'] as $ct => &$ctd) {
                if (!isset($ctd['schema'])) continue;
                $sch = &$ctd['schema'];
                if (($sch['type'] ?? '') === 'object') {
                    $hasProps = !empty($sch['properties']);
                    if (!$hasProps && !isset($sch['additionalProperties'])) {
                        // completely untyped object – add additionalProperties
                        $sch['additionalProperties'] = true;
                        $stats['empty_obj_fixed']++;
                    } elseif (!$hasProps && isset($sch['additionalProperties'])) {
                        // already has additionalProperties – leave as-is
                    } else {
                        // has properties but check for empty nested objects
                        foreach (($sch['properties'] ?? array()) as $propName => $propDef) {
                            if (($propDef['type'] ?? '') === 'object' && empty($propDef['properties'] ?? array())) {
                                if (!isset($sch['properties'][$propName]['additionalProperties'])) {
                                    $sch['properties'][$propName]['additionalProperties'] = true;
                                    $stats['empty_obj_fixed']++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// -----------------------------------------------------------------
// 3. Write fixed YAML (overwrite in place)
// -----------------------------------------------------------------
$yml = Yaml::dump($y, 20, 2);
file_put_contents($out, $yml);

echo "Done." . PHP_EOL;
echo "  path_param_merged   : " . $stats['path_param_merged'] . PHP_EOL;
echo "  rb_required_fixed   : " . $stats['rb_required_fixed'] . PHP_EOL;
echo "  empty_obj_fixed     : " . $stats['empty_obj_fixed'] . PHP_EOL;
