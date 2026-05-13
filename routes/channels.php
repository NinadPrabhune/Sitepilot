<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/



Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
Broadcast::channel('site.{projectId}', function ($user, $projectId) {
    return $user->projects()
        ->where('projects.id', $projectId)
        ->exists();
});
Broadcast::channel('chatify', function ($user) {
    return true; // allow authenticated users
});
