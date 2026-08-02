<?php

namespace App\Service;

/**
 * Ports utils/pushNotification.js. Source not seen - this stub exists so
 * AuthController compiles against a stable interface. Real implementation
 * should use kreait/firebase-bundle (already in composer.json) against
 * FIREBASE_CREDENTIALS_PATH.
 */
interface PushNotificationServiceInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $fcmToken, array $payload): void;

    public function subscribeTokenToTopic(string $fcmToken, string $topic = 'global'): void;
}
