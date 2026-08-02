<?php

namespace App\Service;

/**
 * Ports utils/sendEmail.js. Source not seen - this stub exists so
 * AuthController compiles against a stable interface. Real implementation
 * should use symfony/mailer (already in composer.json) configured against
 * your SMTP_HOST/SMTP_PORT/SMTP_USER/SMTP_PASS/SMTP_SERVICE env vars.
 */
interface EmailServiceInterface
{
    public function send(string $to, string $subject, string $htmlBody): void;
}
