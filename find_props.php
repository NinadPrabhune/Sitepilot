<?php
require __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$y = Yaml::parseFile('uploads/scribe/openapi.yaml');
$yml = Yaml::dump($y, 20, 2);

// Find the approved_dates context in the YAML string
$pos = strpos($yml, 'approved_dates');
if ($pos !== false) {
    // Look for the properties: {  } near approved_dates
    $chunk = substr($yml, max(0, $pos - 400), 800);
    file_put_contents('tmp_chunk.txt', $chunk);
    echo "Chunk written to tmp_chunk.txt\n";
    
    // Search for "properties: {"
    $pos2 = strrpos($chunk, "properties: {");
    if ($pos2 !== false) {
        $near = substr($chunk, $pos2, 60);
        echo "Near properties: " . json_encode($near) . "\n";
    }
    // Search for ALL occurrences of "properties: {"
    $count = 0;
    $offset = 0;
    while (($pos3 = strpos($yml, "properties: {", $offset)) !== false) {
        $count++;
        if ($count <= 5) {
            echo "Occurrence $count at offset $pos3: " . json_encode(substr($yml, $pos3, 40)) . "\n";
        }
        $offset = $pos3 + 1;
    }
    echo "Total 'properties: {' occurrences: $count\n";
} else {
    echo "approved_dates not found in YAML string\n";
}
