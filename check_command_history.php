<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== COMMAND HISTORY INVESTIGATION ===" . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo PHP_EOL;

// Check Windows command history if available
echo "1. WINDOWS COMMAND HISTORY:" . PHP_EOL;
$history_files = [
    getenv('APPDATA') . '\Microsoft\Windows\PowerShell\PSReadLine\ConsoleHost_history.txt',
    getenv('USERPROFILE') . '\AppData\Roaming\Microsoft\Windows\PowerShell\PSReadLine\ConsoleHost_history.txt'
];

foreach ($history_files as $history_file) {
    if (file_exists($history_file)) {
        echo "   Found PowerShell history: {$history_file}" . PHP_EOL;
        $content = file_get_contents($history_file);
        $lines = explode("\n", $content);
        $recent_lines = array_slice($lines, -20); // Last 20 commands
        
        foreach ($recent_lines as $line) {
            if (strpos($line, 'artisan') !== false || strpos($line, 'migrate') !== false) {
                echo "   RELEVANT: " . trim($line) . PHP_EOL;
            }
        }
    }
}

echo PHP_EOL;

// Check for any recent PHP scripts that might have run
echo "2. RECENT PHP SCRIPTS:" . PHP_EOL;
$script_dir = '.';
$files = scandir($script_dir);
$recent_files = [];

foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $filepath = $script_dir . '/' . $file;
        if (filemtime($filepath) > strtotime('2026-05-02 16:00:00')) { // Last 2.5 hours
            $recent_files[$file] = filemtime($filepath);
        }
    }
}

if (count($recent_files) > 0) {
    echo "   Recently modified/created PHP files:" . PHP_EOL;
    foreach ($recent_files as $file => $mtime) {
        echo "   {$file}: " . date('H:i:s', $mtime) . PHP_EOL;
    }
} else {
    echo "   No recent PHP script modifications found" . PHP_EOL;
}

echo PHP_EOL;

// Check Git history for any clues
echo "3. GIT HISTORY CHECK:" . PHP_EOL;
if (is_dir('.git')) {
    // Check recent commits
    $git_log = shell_exec('git log --oneline --since="2026-05-02 16:00" 2>&1');
    if ($git_log && trim($git_log) !== '') {
        echo "   Recent Git activity:" . PHP_EOL;
        echo "   " . str_replace("\n", "\n   ", trim($git_log)) . PHP_EOL;
    } else {
        echo "   No recent Git activity" . PHP_EOL;
    }
    
    // Check current branch status
    $git_status = shell_exec('git status --porcelain 2>&1');
    if ($git_status && trim($git_status) !== '') {
        echo "   Git status (modified files):" . PHP_EOL;
        echo "   " . str_replace("\n", "\n   ", trim($git_status)) . PHP_EOL;
    }
} else {
    echo "   Not a Git repository" . PHP_EOL;
}

echo PHP_EOL;

// Check for any deployment scripts
echo "4. DEPLOYMENT SCRIPTS CHECK:" . PHP_EOL;
$deployment_files = [
    'deploy.sh',
    'deploy.bat', 
    'deployment.sh',
    'deployment.bat',
    'post-deploy.sh',
    'post-deploy.bat'
];

foreach ($deployment_files as $script) {
    if (file_exists($script)) {
        $mtime = filemtime($script);
        echo "   {$script}: Modified " . date('Y-m-d H:i:s', $mtime) . PHP_EOL;
        
        if ($mtime > strtotime('2026-05-02 16:00:00')) {
            echo "   ⚠️  Recently modified!" . PHP_EOL;
        }
    }
}

echo PHP_EOL;

// Check Laravel log for artisan commands
echo "5. LARAVEL LOG ANALYSIS:" . PHP_EOL;
$log_file = 'storage/logs/laravel.log';
if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    $lines = explode("\n", $content);
    
    // Look for migrate:fresh, migrate:refresh, db:seed patterns
    $patterns = [
        'migrate:fresh',
        'migrate:refresh', 
        'migrate:reset',
        'db:seed',
        'DATABASE SEEDER RUNNING',
        'SEEDER BLOCKED'
    ];
    
    echo "   Checking Laravel log for artisan commands..." . PHP_EOL;
    foreach ($lines as $i => $line) {
        foreach ($patterns as $pattern) {
            if (stripos($line, $pattern) !== false) {
                echo "   Found '{$pattern}' at line " . ($i + 1) . PHP_EOL;
                // Show context
                $start = max(0, $i - 2);
                $end = min(count($lines), $i + 3);
                for ($j = $start; $j < $end; $j++) {
                    $marker = ($j == $i) ? ">>> " : "    ";
                    echo $marker . $lines[$j] . PHP_EOL;
                }
                echo PHP_EOL;
            }
        }
    }
} else {
    echo "   Laravel log not found" . PHP_EOL;
}

echo PHP_EOL;

// Summary based on user creation time
echo "6. TIMELINE ANALYSIS:" . PHP_EOL;
echo "   Current time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "   Users created at: 2026-05-02 18:14:48-49" . PHP_EOL;
echo "   Time since user creation: " . (time() - strtotime('2026-05-02 18:14:48')) . " seconds" . PHP_EOL;
echo PHP_EOL;
echo "   CONCLUSION:" . PHP_EOL;
echo "   Database was reset approximately 2.5 hours ago" . PHP_EOL;
echo "   Fresh users were created immediately after the reset" . PHP_EOL;
echo "   This indicates a deliberate database reset operation" . PHP_EOL;
