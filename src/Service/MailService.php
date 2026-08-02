<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $frontendUrl
    ) {
    }






    public function sendVerificationEmail(
        User $user,
        string $otp
    ): void {


        $email = (new Email())

            ->from('no-reply@goon.com')

            ->to($user->getEmail())

            ->subject(
                'Email Verification 🚌'
            )

            ->html(
                "
                <h2>Hello {$user->getName()}</h2>

                <p>
                Your verification code is:
                </p>

                <h1>
                {$otp}
                </h1>

                <p>
                This code expires in 10 minutes.
                </p>
                "
            );


        $this->mailer->send($email);

    }







    public function sendWelcomeEmail(
        User $user
    ): void {


        $email = (new Email())

            ->from('no-reply@goon.com')

            ->to($user->getEmail())

            ->subject(
                'Welcome Back 🚌'
            )

            ->html(
                "
                <h2>
                Welcome back {$user->getName()}
                </h2>

                <p>
                You have successfully logged into GoOn.
                </p>
                "
            );


        $this->mailer->send($email);

    }







    public function sendPasswordResetEmail(
        User $user,
        string $token
    ): void {


        $resetUrl =
            $this->frontendUrl .
            "/reset-password/" .
            $token;



        $email = (new Email())

            ->from('no-reply@goon.com')

            ->to($user->getEmail())

            ->subject(
                'Reset Your Password'
            )

            ->html(
                "
                <h2>
                Password Reset
                </h2>

                <p>
                Click below to reset your password.
                </p>

                <a href='{$resetUrl}'>
                Reset Password
                </a>

                <p>
                This link expires in one hour.
                </p>
                "
            );


        $this->mailer->send($email);

    }







    public function sendVerifiedEmail(
        User $user
    ): void {


        $email = (new Email())

            ->from('no-reply@goon.com')

            ->to($user->getEmail())

            ->subject(
                'Successful Verification 🚌'
            )

            ->html(
                "
                <h2>
                Account Verified
                </h2>

                <p>
                Congratulations {$user->getName()},
                your account is now verified.
                </p>
                "
            );


        $this->mailer->send($email);

    }

}