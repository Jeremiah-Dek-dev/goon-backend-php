<?php

namespace App\Service;

/**
 * TODO: replace with a real symfony/mailer-backed implementation once
 * utils/sendEmail.js and utils/EmailTemplates.js are available - templates
 * (EmailOTP, EmailWelcome, ResendEmail, VerifiedEmail) need porting to
 * Twig email templates or equivalent PHP template functions.
 */
class NullEmailService implements EmailServiceInterface
{
    public function send(string $to, string $subject, string $htmlBody): void
    {
        // Intentionally a no-op placeholder - do not use in production.
    }
}
