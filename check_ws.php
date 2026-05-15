<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Work Spaces ===\n";
$ws = DB::table('work_spaces')->get();
foreach ($ws as $w) {
    echo "ID: {$w->id}, Name: {$w->name}, Slug: {$w->slug}, Created: {$w->created_by}\n";
}

echo "\n=== Users ===\n";
$users = DB::table('users')->select('id','name','type','active_workspace','workspace_id')->get();
foreach ($users as $u) {
    echo "ID: {$u->id}, Name: {$u->name}, Type: {$u->type}, ActiveWS: {$u->active_workspace}, WS: {$u->workspace_id}\n";
}

echo "\n=== Projects ===\n";
$projects = DB::table('projects')->select('id','name','workspace')->get();
foreach ($projects as $p) {
    echo "ID: {$p->id}, Name: {$p->name}, Workspace: {$p->workspace}\n";
}

echo "\n=== User Projects ===\n";
$up = DB::table('user_projects')->get();
foreach ($up as $u) {
    echo "UserID: {$u->user_id}, ProjectID: {$u->project_id}, Active: {$u->is_active}\n";
}