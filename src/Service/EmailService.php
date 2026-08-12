<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Symfony\Component\Mailer\Transport\TransportInterface;

class EmailService
{

    public function __construct(
        private readonly MailerInterface $asyncMailer,
        private readonly Environment $twig,
        private TransportInterface $syncMailer,
        private readonly string $fromEmail,
        private readonly string $frontendUrl, 
        private readonly string $logo,
        private readonly string $fromAddress,
    ) {
    }



    /**
     * Send OTP verification email
     */
    public function sendOTP(
        User $user,
        string $otp
    ): void {


        $email = (new TemplatedEmail())

            ->from(
                new Address(
                    $this->fromAddress,
                    'TOLI-TOLI'
                )
            )

            ->to($user->getEmail())

            ->subject(
                'Verify your TOLI-TOLI account'
            )

            ->htmlTemplate(
                'emails/otp.html.twig'
            )

            ->context([

                'name' =>
                    $user->getName(),

                'otp' =>
                    $otp,

                'frontend_url' =>
                    $this->frontendUrl,

                'logo' =>
                    $this->logo,

            ]);



        $this->syncMailer->send($email);

    }






        /**
     * Send welcome email after successful verification
     */
    public function sendWelcome(User $user): void
    {

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Welcome to TOLI-TOLI 🎉')
            ->html(
                $this->twig->render(
                    'emails/welcome.html.twig',
                    [
                        'name' => $user->getName(),

                        'frontend_url' => $this->frontendUrl,

                        'logo' => $this->logo,
                    ]
                )
            );


        $this->asyncMailer
            ->send($email);

    }




    public function sendAdminInvite(
    string $email,
    ?string $name,
    string $inviteLink,
    string $expiresAt
): void {
    $message = (new TemplatedEmail())
        ->from(
            new Address(
                $this->fromAddress,
                'TOLI-TOLI'
            )
        )
        ->to($email)
        ->subject('GoOn Admin Invitation')
        ->htmlTemplate('emails/admin_invite.html.twig')
        ->context([
            'name' => $name,
            'invite_link' => $inviteLink,
            'expires_at' => $expiresAt,
            'frontend_url' => $this->frontendUrl,
            'logo' => $this->logo,
        ]);

    $this->syncMailer->send($message);
}





/**
 * Send administrator password reset email.
 */
public function sendUserPasswordReset(
    User $user,
    string $resetLink,
    \DateTimeImmutable $expiresAt
): void {
    $email = (new TemplatedEmail())
        ->from(
            new Address(
                $this->fromAddress,
                'TOLI-TOLI'
            )
        )
        ->to($user->getEmail())
        ->subject('Reset your TOLI-TOLI user password')
        ->htmlTemplate(
            'emails/user_password_reset.html.twig'
        )
        ->context([
            'name' => $user->getName(),
            'reset_link' => $resetLink,
            'expires_at' => $expiresAt,
            'frontend_url' => $this->frontendUrl,
            'logo' => $this->logo,
        ]);

    $this->syncMailer->send($email);
}




/**
 * Send administrator password reset email.
 */
public function sendAdminPasswordReset(
    User $user,
    string $resetLink,
    \DateTimeImmutable $expiresAt
): void {
    $email = (new TemplatedEmail())
        ->from(
            new Address(
                $this->fromAddress,
                'TOLI-TOLI'
            )
        )
        ->to($user->getEmail())
        ->subject('Reset your TOLI-TOLI admin password')
        ->htmlTemplate(
            'emails/admin_password_reset.html.twig'
        )
        ->context([
            'name' => $user->getName(),
            'reset_link' => $resetLink,
            'expires_at' => $expiresAt,
            'frontend_url' => $this->frontendUrl,
            'logo' => $this->logo,
        ]);

    $this->syncMailer->send($email);
}
}
