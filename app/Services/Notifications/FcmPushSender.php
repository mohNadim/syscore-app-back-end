<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

/**
 * Sends notifications through Firebase Cloud Messaging and prunes tokens FCM reports as dead.
 */
class FcmPushSender implements PushSender
{
    public function __construct(private readonly Messaging $messaging) {}

    public function send(Notification $notification): bool
    {
        try {
            $user = $notification->user;
            $tokens = $user->devices()->pluck('push_token')->all();

            if ($tokens === []) {
                return false;
            }

            $isEnglish = $user->preferred_lang === 'en';
            $title = $isEnglish ? ($notification->title_en ?: $notification->title_ar) : ($notification->title_ar ?: $notification->title_en);
            $body = $isEnglish ? ($notification->body_en ?: $notification->body_ar) : ($notification->body_ar ?: $notification->body_en);

            $data = collect($notification->data ?? [])
                ->map(fn (mixed $value): string => is_scalar($value) ? (string) $value : (string) json_encode($value))
                ->put('notification_id', (string) $notification->id)
                ->put('type', $notification->type)
                ->all();

            $message = CloudMessage::new()
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            $deadTokens = array_merge($report->unknownTokens(), $report->invalidTokens());

            if ($deadTokens !== []) {
                $user->devices()->whereIn('push_token', $deadTokens)->delete();
            }

            return $report->validTokens() !== [];
        } catch (Throwable $exception) {
            Log::warning('FCM push failed', ['notification_id' => $notification->id, 'error' => $exception->getMessage()]);

            return false;
        }
    }
}
