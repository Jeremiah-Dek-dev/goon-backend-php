<?php

namespace App\Service;

/**
 * TODO: replace with a real kreait/firebase-bundle-backed implementation
 * once utils/pushNotification.js is available.
 */
class NullPushNotificationService implements PushNotificationServiceInterface
{
    public function send(string $fcmToken, array $payload): void
    {
        // Intentionally a no-op placeholder - do not use in production.
    }

    public function subscribeTokenToTopic(string $fcmToken, string $topic = 'global'): void
    {
        // Intentionally a no-op placeholder - do not use in production.
    }
}
