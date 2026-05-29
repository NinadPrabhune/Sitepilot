<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$yml = Yaml::dump(Yaml::parseFile('uploads/scribe/openapi.yaml'), 20, 2);

$n1 = substr_count($yml, 'properties: {  }');
$n2 = substr_count($yml, "properties: {  }\n");
echo "Count with plain search: $n1\n";
echo "Count with newline: $n2\n\n";

// Print the approved_dates context
$p = strpos($yml, 'approved_dates');
if ($p !== false) {
    echo "approved_dates at offset $p in YAML string\n";
    // Find the properties line near approved_dates
    $near = substr($yml, max(0, $p-600), 900);
    $propsPos = strrpos($near, 'properties: {  }');
    if ($propsPos !== false) {
        echo "Near properties: " . json_encode(substr($near, $propsPos, 60)) . PHP_EOL;
    } else {
        echo "No 'properties: {  }' near approved_dates. Context:\n";
        echo substr($near, 0, 600);
    }
}
// Count empty object schemas in requestBody context
echo "\n=== All empty props patterns ===";
preg_match_all('/properties: \{  \}\n[ ]*[a-zA-Z]/', $yml, $all, PREG_OFFSET_CAPTURE);
echo "Found " . count($all[0]) . " matches\n";
foreach ($all[0] as $i => $match) {
    if ($i >= 5) break;
    $ctx = substr($yml, max(0, $match[1]-30), 80);
    echo "  [" . ($i+1) . "] offset=" . $match[1] . ": " . json_encode($match[0]) . "\n  CTX: ..." . substr($ctx, 0, 80) . "\n";
}
