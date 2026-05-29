<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml', Yaml::PARSE_CONSTANT);
echo "openapi: " . $y['openapi'] . PHP_EOL;
echo "paths: " . count($y['paths']) . PHP_EOL;
echo PHP_EOL;

$issues = [];

foreach ($y['paths'] as $p => $ops) {
    foreach (['get','post','put','patch','delete'] as $m) {
        if (!isset($ops[$m])) continue;
        $o = $ops[$m];

        // 1. Missing summary
        if (empty($o['summary'])) $issues[] = "$p $m: missing summary";
        if (empty($o['description'])) $issues[] = "$p $m: missing description";

        // 2. POST/PUT/PATCH without requestBody
        if (in_array($m, ['post','put','patch']) && !isset($o['requestBody'])) {
            $issues[] = "$p $m: no requestBody";
        }

        // 3. Path param in parent or child
        $allParams = $o['parameters'] ?? [];
        $pathHasVar = preg_match('/\{[^}]+\}/', $p);
        if ($pathHasVar) {
            $hasPathParam = false;
            foreach ($allParams as $pp) {
                if (($pp['in'] ?? '') === 'path') { $hasPathParam = true; break; }
            }
            if (!$hasPathParam) $issues[] = "$p $m: path has variable but no path params declared";
        }

        // 4. Each path param should have type/format
        foreach ($allParams as $pp) {
            if (($pp['in'] ?? '') === 'path') {
                $n = $pp['name'] ?? '?';
                $hasType = isset($pp['schema']) && isset($pp['schema']['type']);
                if (!$hasType) $issues[] = "$p $m param $n: missing schema.type";
            }
        }

        // 5. Check requestBody has content
        if (isset($o['requestBody'])) {
            $rb = $o['requestBody'];
            $hasContent = is_array($rb['content'] ?? null) && !empty($rb['content']);
            $req = $rb['required'] ?? false;
            $contentType = array_keys($rb['content'] ?? []);
            if (!$hasContent) $issues[] = "$p $m: requestBody has no content";
            if (!$req) $issues[] = "$p $m: requestBody not marked required";
            // Check schema presence inside content
            if ($hasContent) {
                foreach ($contentType as $ct) {
                    if (!isset($rb['content'][$ct]['schema'])) {
                        $issues[] = "$p $m: requestBody/$ct missing schema";
                    }
                }
            }
        }
    }
}

echo "=== ISSUES FOUND ===" . PHP_EOL;
echo implode(PHP_EOL, $issues) . PHP_EOL;
echo "Total: " . count($issues) . PHP_EOL;

// Also check security Schemes to see if Bearer is defined
echo PHP_EOL . "=== SECURITY ===" . PHP_EOL;
$sec = $y['components']['securitySchemes'] ?? [];
echo "Schemes: " . implode(', ', array_keys($sec)) . PHP_EOL;
echo "Global security: " . (isset($y['security']) ? count($y['security']) : 0) . " entries" . PHP_EOL;
