<?php
// Debug: exact context from Symfony YAML dump
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$yml = Yaml::dump(Yaml::parseFile('uploads/scribe/openapi.yaml'), 20, 2);
$pos = strpos($yml, 'approved_dates');
$ctx = substr($yml, max(0, $pos - 600), 900);

$propsPos = strrpos($ctx, 'properties: {  }');
if ($propsPos !== false) {
    $chunk = substr($ctx, $propsPos, 80);
    echo "Full byte dump of context:\n";
    for ($i = 0; $i < strlen($chunk); $i++) {
        $c = ord($chunk[$i]);
        $label = match ($c) {
            10 => 'LF',
            32 => 'SP',
            default => "'" . chr($c) . "'",
        };
        printf("%3d ", $c);
        if (($i + 1) % 16 === 0) echo " | $label\n";
        else echo "$label ";
    }
    echo "\n\n";

    echo "String repr: " . json_encode($chunk) . "\n";
    
    // Try various patterns
    $patterns = [
        '/\n[ ]+properties:\s*\{\s*\}\n/',
        '/\s*properties:\s*\{\s*\}\n/',
        '/properties:\s*\{\s*\}\n/',
    ];
    foreach ($patterns as $idx => $pat) {
        $m = array();
        if (preg_match($pat, $chunk, $m)) {
            echo "Pattern $idx MATCHED: " . json_encode($m[0]) . "\n";
        } else {
            echo "Pattern $idx NOT matched\n";
        }
    }
}
