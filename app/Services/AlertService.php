<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Send alert to Slack webhook
     * 
     * @param string $event Event type
     * @param string $message Alert message
     * @param array $context Additional context
     * @return bool
     */
    public function sendSlack(string $event, string $message, array $context = []): bool
    {
        $webhookUrl = config('services.slack.webhook_url');
        
        if (empty($webhookUrl)) {
            Log::warning('Slack webhook URL not configured', ['event' => $event]);
            return false;
        }

        $payload = [
            'text' => "🚨 LEDGER ALERT: {$event}",
            'attachments' => [
                [
                    'color' => 'danger',
                    'title' => $message,
                    'fields' => array_map(function ($key, $value) {
                        return [
                            'title' => $key,
                            'value' => is_array($value) ? json_encode($value) : $value,
                            'short' => false,
                        ];
                    }, array_keys($context), $context),
                    'footer' => config('app.name'),
                    'ts' => now()->timestamp,
                ],
            ],
        ];

        try {
            $response = Http::post($webhookUrl, $payload);
            
            if ($response->successful()) {
                Log::info('Slack alert sent', ['event' => $event]);
                return true;
            } else {
                Log::error('Failed to send Slack alert', [
                    'event' => $event,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception sending Slack alert', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send email alert
     * 
     * @param string $event Event type
     * @param string $message Alert message
     * @param array $context Additional context
     * @return bool
     */
    public function sendEmail(string $event, string $message, array $context = []): bool
    {
        $recipients = config('services.alerts.email_recipients', []);
        
        if (empty($recipients)) {
            Log::warning('Alert email recipients not configured', ['event' => $event]);
            return false;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Event: {$event}\n\nMessage: {$message}\n\nContext:\n" . json_encode($context, JSON_PRETTY_PRINT),
                function ($message) use ($recipients, $event) {
                    $message->to($recipients)
                        ->subject("[LEDGER ALERT] {$event}");
                }
            );
            
            Log::info('Email alert sent', ['event' => $event]);
            return true;
        } catch (\Exception $e) {
            Log::error('Exception sending email alert', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send alert via configured channels
     * 
     * @param string $channel Channel (slack, email, both)
     * @param string $event Event type
     * @param string $message Alert message
     * @param array $context Additional context
     * @return void
     */
    public function send(string $channel, string $event, string $message, array $context = [])
    {
        if ($channel === 'slack' || $channel === 'both') {
            $this->sendSlack($event, $message, $context);
        }

        if ($channel === 'email' || $channel === 'both') {
            $this->sendEmail($event, $message, $context);
        }
    }
}
