<?php
// app/Services/FCMService.php
namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class FCMService
{
    protected string $endpoint;
    protected string $jsonPath;

    public function __construct()
    {
        // Load values from config/firebase.php or directly from env
        $projectId = config('firebase.project_id', env('FIREBASE_PROJECT_ID'));
        $this->endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $this->jsonPath = config('firebase.credentials', env('FIREBASE_CREDENTIALS', storage_path('app/firebase-service-account.json')));
    }

    /**
     * Get OAuth2 access token from service account JSON
     */
    protected function getAccessToken(): string
    {
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $creds = new ServiceAccountCredentials($scopes, $this->jsonPath);
        $token = $creds->fetchAuthToken();
        return $token['access_token'];
    }

    /**
     * Generic method to post payload to FCM
     */
    protected function postToFcm(array $payload): array
    {
        // Guard: Check if notifications are enabled
        if (!config('app.send_notification')) {
            \Log::info('Notifications disabled via SEND_NOTIFICATION flag - skipping FCM API call');
            return ['skipped' => true, 'reason' => 'notifications_disabled'];
        }

        try {
            $response = Http::withToken($this->getAccessToken())
                ->post($this->endpoint, $payload)
                ->json();

            // Alert on Firebase quota errors
            if (isset($response['error']) && $response['error']['code'] === 429) {
                \Log::critical('Firebase quota exceeded - push notifications failing', [
                    'error' => $response['error'],
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            \Log::error('FCM API call failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->endpoint,
            ]);
            throw $e;
        }
    }

    /**
     * Send notification to a single token
     */
    public function sendNotification(string $token, string $title, string $body, array $data = []): array
    {
        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
        ];

        // Only include data field if it's not empty (FCM API v1 doesn't accept empty arrays)
        if (!empty($data)) {
            $message['data'] = $data;
        }

        $payload = ['message' => $message];

        return $this->postToFcm($payload);
    }

    /**
     * Send notification to all devices of a user
     */
    public function sendToUser(User $user, array $notification, array $data = []): array
    {
        $results = [];
        foreach ($user->deviceTokens()->pluck('token') as $token) {
            $message = [
                'token' => $token,
                'notification' => $notification,
            ];

            // Only include data field if it's not empty (FCM API v1 doesn't accept empty arrays)
            if (!empty($data)) {
                $message['data'] = $data;
            }

            $payload = ['message' => $message];
            $results[] = $this->postToFcm($payload);
        }
        return $results;
    }

    /**
     * Send notification to a topic (broadcast)
     */
    public function sendToTopic(string $topic, array $notification, array $data = []): array
    {
        $message = [
            'topic' => $topic,
            'notification' => $notification,
        ];

        // Only include data field if it's not empty (FCM API v1 doesn't accept empty arrays)
        if (!empty($data)) {
            $message['data'] = $data;
        }

        $payload = ['message' => $message];

        return $this->postToFcm($payload);
    }

    /**
     * Send bulk notification to multiple device tokens using multicast API
     */
    public function sendBulkNotification(array $deviceTokens, string $title, string $message, array $data = []): array
    {
        // Split tokens into chunks of 500 (Firebase multicast limit)
        $chunks = array_chunk($deviceTokens, 500);
        $results = [];
        $invalidTokens = [];

        foreach ($chunks as $chunk) {
            $message = [
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                ],
                'tokens' => $chunk,
            ];

            // Only include data field if it's not empty (FCM API v1 doesn't accept empty arrays)
            if (!empty($data)) {
                $message['data'] = $data;
            }

            $payload = ['message' => $message];

            $response = $this->postToFcm($payload);
            $results[] = $response;

            // Clean up invalid tokens from response
            if (isset($response['results'])) {
                foreach ($response['results'] as $index => $result) {
                    if (isset($result['error']) && in_array($result['error'], ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                        $invalidTokens[] = $chunk[$index];
                    }
                }
            }
        }

        // Remove invalid tokens from database
        if (!empty($invalidTokens)) {
            \App\Models\DeviceToken::whereIn('token', $invalidTokens)->delete();
            \Log::info('Cleaned up invalid FCM tokens', ['count' => count($invalidTokens)]);
        }

        return $results;
    }
}
