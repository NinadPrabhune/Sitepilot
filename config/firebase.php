<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'credentials' => env('FIREBASE_CREDENTIALS') ? base_path(env('FIREBASE_CREDENTIALS')) : null,
];
