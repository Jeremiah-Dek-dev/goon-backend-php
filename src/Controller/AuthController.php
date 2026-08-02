<?php

namespace App\Controller;

use App\Entity\OTP;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\NotificationType;
use App\Enum\UserRole;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Service\CookieService;
use App\Service\EmailServiceInterface;
use App\Service\OtpGenerator;
use App\Service\PushNotificationServiceInterface;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ported from the pre-authMiddleware section of routes/UserRoute.js +
 * controllers/UserController.js (register/login/forgotPassword/
 * resetPassword/verifyOTP/resendOTP/googleAuthCallback).
 *
 * `userTokenRefresh` (POST /refresh-token) is still a stub - its body
 * wasn't in the source provided.
 */
#[Route('/api/user')]
class AuthController extends AbstractApiController
{
    public function __construct(
        #[Autowire(service: 'limiter.otp')]
        private readonly RateLimiterFactory $otpLimiter,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly OTPRepository $otpRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenService $tokens,
        private readonly CookieService $cookies,
        private readonly ActivityLogService $activityLog,
        private readonly EmailServiceInterface $mailer,
        private readonly PushNotificationServiceInterface $push,
        private readonly OtpGenerator $otpGenerator,
    ) {
    }

    #[Route('/register', name: 'user_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $googleId = $data['googleId'] ?? null;
        $fcmToken = $data['fcmToken'] ?? null;

        // Was: validator.isEmail(email)
        $emailViolations = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);
        if (count($emailViolations) > 0) {
            return $this->json(['success' => false, 'message' => 'Invalid email format'], 400);
        }

        // Was: validator.isStrongPassword(password) - default validator.js rule:
        // min 8 chars, >=1 lowercase, >=1 uppercase, >=1 number, >=1 symbol.
        // NOTE: the Node error message only mentions upper/lower/special,
        // not numbers, even though isStrongPassword's default also requires
        // a number - preserved the same (possibly misleading) message text.
        if (!$this->isStrongPassword((string) $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Password must be at least 8 characters long and include uppercase, lowercase, and a special character.',
            ], 403);
        }

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json(['success' => false, 'message' => 'User already exists'], 400);
        }

        // NOTE: Node hashed with bcrypt cost 10 here specifically, vs cost
        // 12 in resetPassword below - inconsistent in the original. This
        // uses the globally configured hasher (cost 12, security.yaml),
        // which is a deliberate, flagged deviation (stronger, not weaker).
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $password));
        $user->setVerified(false);
        $user->setGoogleId($googleId);
        $user->setFcmToken($fcmToken);
        $this->em->persist($user);

        $roleAssignment = new UserRoleAssignment();
        $roleAssignment->setUser($user);
        $roleAssignment->setRole(UserRole::USER);
        $this->em->persist($roleAssignment);

        $otp = new OTP();
        $otp->setUser($user);
        $otp->setOtp($this->otpGenerator->generate());
        $otp->setExpiresAt(new \DateTimeImmutable('+10 minutes'));
        $this->em->persist($otp);

        $this->em->flush();

        // TODO: port EmailOTP template from utils/EmailTemplates.js (not seen)
        $this->mailer->send($user->getEmail(), 'Email Verification 🚌', '');

        if ($user->getFcmToken() !== null) {
            try {
                $this->push->send($user->getFcmToken(), [
                    'title' => "Welcome, {$user->getName()}!",
                    'body' => 'You have successfully registered in TOLI-TOLI. Check your dashboard for more updates 😊',
                    'data' => ['type' => 'register', 'tag' => 'register', 'url' => '/myBookings'],
                ]);
                $this->push->subscribeTokenToTopic($user->getFcmToken());
            } catch (\Throwable) {
                // Matches Node: push failure doesn't fail registration.
            }
        }

        $this->activityLog->log(
            $user,
            'REGISTERED SUCCESSFULLY',
            "{$user->getName()} registered in successfully",
        );

        return $this->json([
            'success' => true,
            'message' => 'OTP sent to your email',
            'redirect' => '/verify-otp',
            'userId' => $user->getId(),
        ]);
    }

    #[Route('/login', name: 'user_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null) {
            return $this->json([
                'success' => false,
                'message' => 'No account found with this email. Please register first.',
            ], 404);
        }

        if (!$user->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Please verify your email to continue.',
                'redirect' => '/verify-otp',
            ], 403);
        }

        if ($user->getPassword() === null) {
            return $this->json(['success' => false, 'message' => 'Use Google login for this account.'], 400);
        }

        if ($user->getLockUntil() !== null && $user->getLockUntil() > new \DateTimeImmutable()) {
            return $this->json([
                'success' => false,
                'message' => '⚠️ Account is temporarily locked due to too many failed attempts. Try again later.',
            ], 423);
        }

        if (!$this->passwordHasher->isPasswordValid($user, (string) $password)) {
            $failedAttempts = $user->getFailedLoginAttempts() + 1;
            $lockUntil = null;

            if ($failedAttempts >= 5) {
                $lockUntil = new \DateTimeImmutable('+30 minutes');
            }

            $user->setFailedLoginAttempts($failedAttempts);
            $user->setLockUntil($lockUntil);
            $this->em->flush();

            return $this->json([
                'success' => false,
                'message' => $lockUntil !== null
                    ? 'Account locked due to too many failed attempts.'
                    : 'Incorrect credentials.',
            ], $lockUntil !== null ? 423 : 401);
        }

        $user->setFailedLoginAttempts(0);
        $user->setLockUntil(null);

        $accessToken = $this->tokens->signAccessToken($user);
        $refreshTokenRaw = $this->tokens->signRefreshToken($user);

        // Was: hash the refresh token + prune to last 4 valid tokens before
        // storing the new one. NOTE: Node computed `validTokens` (filtered,
        // sliced) but never actually deleted/revoked the pruned-out excess
        // tokens from the DB - only ever appended. Preserved as-is (append
        // a RefreshToken row) rather than "fixing" silently; flagging that
        // unbounded RefreshToken row growth is a pre-existing issue worth
        // addressing deliberately, e.g. with a cleanup job.
        $refreshTokenEntity = new \App\Entity\RefreshToken();
        $refreshTokenEntity->setUser($user);
        $refreshTokenEntity->setToken($this->passwordHasher->hashPassword($user, $refreshTokenRaw));
        $refreshTokenEntity->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $refreshTokenEntity->setRevoked(false);
        $this->em->persist($refreshTokenEntity);
        $this->em->flush();

        // TODO: port EmailWelcome template from utils/EmailTemplates.js (not seen)
        $this->mailer->send($user->getEmail(), 'Welcome Back 🚌', '');

        $notification = new \App\Entity\Notification();
        $notification->setUser($user);
        $notification->setTitle("Welcome back, {$user->getName()}!");
        $notification->setBody('You have successfully logged in to GoOn. Check your dashboard for updates 😊');
        $notification->setType(NotificationType::LOGIN);
        $notification->setIsRead(false);
        $this->em->persist($notification);
        $this->em->flush();

        if ($user->getFcmToken() !== null) {
            try {
                $this->push->send($user->getFcmToken(), [
                    'title' => "Welcome back, {$user->getName()}!",
                    'body' => 'You have successfully logged in to GoOn. Check your dashboard for updates 😊',
                    'data' => ['type' => 'login', 'tag' => 'login', 'url' => '/myBookings'],
                ]);
                $this->push->subscribeTokenToTopic($user->getFcmToken());
            } catch (\Throwable) {
                // Matches Node: push failure doesn't fail login.
            }
        }

        $response = $this->json([
            'success' => true,
            'roles' => $user->getRoles(),
            'userId' => (string) $user->getId(),
            'message' => 'Login successful!',
        ]);

        $this->cookies->setAppCookie($response, 'userRefreshToken', $refreshTokenRaw, $this->tokens->getRefreshTokenTtlSeconds());
        $this->cookies->setAppCookie($response, 'userAccessToken', $accessToken, $this->tokens->getAccessTokenTtlSeconds());

        $this->activityLog->log($user, 'LOGIN SUCCESSFULLY', "{$user->getName()} logged in successfully");

        return $response;
    }

    #[Route('/forgot-password', name: 'user_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $user = $this->userRepository->findOneBy(['email' => $data['email'] ?? null]);

        if ($user === null) {
            return $this->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $resetToken = bin2hex(random_bytes(32));
        $user->setResetToken($resetToken);
        $user->setResetTokenExpires(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $resetUrl = sprintf('%s/reset-password/%s', $_ENV['FRONTEND_URL'] ?? '', $resetToken);
        $this->mailer->send(
            $user->getEmail(),
            'Reset Your Password',
            "Click the link below to reset your password:\n\n{$resetUrl}\n\nThis link will expire in 1 hour."
        );

        return $this->json(['success' => true, 'message' => 'Password reset link sent to your email.']);
    }

    #[Route('/reset-password/{token}', name: 'user_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, string $token): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $password = $data['password'] ?? null;

        $user = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.resetToken = :token')
            ->andWhere('u.resetTokenExpires > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        if ($user === null) {
            return $this->json(['success' => false, 'message' => 'Invalid or expired token.'], 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $password));
        $user->setResetToken(null);
        $user->setResetTokenExpires(null);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Password reset successful✅.']);
    }

    #[Route('/verify-otp', name: 'user_verify_otp', methods: ['POST'])]
    public function verifyOtp(Request $request): JsonResponse
    {
        $limit = $this->otpLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['message' => 'Too many OTP attempts. Please try again later.'], 429);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $userId = $data['userId'] ?? null;
        $otp = $data['otp'] ?? null;
        // NOTE: Node also accepted a `token` (a signed access token) as an
        // alternative to userId+otp, decoding it to get the user id and
        // SKIPPING the OTP check entirely in that branch. Preserved as-is,
        // but flagging it as an odd trust boundary: anyone holding a valid
        // access token can silently mark the account verified without an OTP.
        $token = $data['token'] ?? null;

        $userIdNumber = null;
        if ($token !== null) {
            try {
                $payload = json_decode(
                    base64_decode(explode('.', (string) $token)[1] ?? ''),
                    true
                );
                $userIdNumber = isset($payload['id']) ? (int) $payload['id'] : null;
            } catch (\Throwable) {
                $userIdNumber = null;
            }
        } elseif ($userId !== null) {
            $userIdNumber = (int) $userId;
        }

        if ($userIdNumber === null) {
            return $this->json(['success' => false, 'message' => 'Invalid user ID or missing data']);
        }

        $user = $this->userRepository->find($userIdNumber);
        if ($user === null) {
            return $this->json(['success' => false, 'message' => 'User not found']);
        }

        if ($token === null) {
            $otpRecord = $this->otpRepository->createQueryBuilder('o')
                ->andWhere('o.user = :user')
                ->andWhere('o.otp = :otp')
                ->andWhere('o.expiresAt >= :now')
                ->setParameter('user', $user)
                ->setParameter('otp', $otp)
                ->setParameter('now', new \DateTimeImmutable())
                ->getQuery()
                ->getOneOrNullResult();

            if ($otpRecord === null) {
                return $this->json(['success' => false, 'message' => 'Invalid or expired OTP']);
            }
        }

        $user->setVerified(true);
        foreach ($user->getOtps() as $otpEntity) {
            $this->em->remove($otpEntity);
        }
        $this->em->flush();

        // TODO: port VerifiedEmail template from utils/EmailTemplates.js (not seen)
        $this->mailer->send($user->getEmail(), 'Successful Verification 🚌', '');

        $accessToken = $this->tokens->signAccessToken($user);
        $refreshTokenRaw = $this->tokens->signRefreshToken($user);

        $response = $this->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'redirect' => sprintf(
                '%s/?name=%s&email=%s',
                $_ENV['FRONTEND_URL'] ?? '',
                urlencode($user->getName()),
                urlencode($user->getEmail())
            ),
            'token' => $accessToken,
            'user' => ['name' => $user->getName(), 'email' => $user->getEmail()],
        ]);

        $this->cookies->setAppCookie($response, 'userAccessToken', $accessToken, $this->tokens->getAccessTokenTtlSeconds());
        $this->cookies->setAppCookie($response, 'userRefreshToken', $refreshTokenRaw, $this->tokens->getRefreshTokenTtlSeconds());

        $this->activityLog->log($user, 'VERIFIED SUCCESSFULLY', "{$user->getName()} verified in successfully");

        return $response;
    }

    #[Route('/resend-otp', name: 'user_resend_otp', methods: ['POST'])]
    public function resendOtp(Request $request): JsonResponse
    {
        $limit = $this->otpLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['message' => 'Too many OTP attempts. Please try again later.'], 429);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $userId = $data['userId'] ?? null;

        if ($userId === null) {
            return $this->json(['success' => false, 'message' => 'User ID is missing']);
        }

        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return $this->json(['success' => false, 'message' => 'User not found']);
        }

        if ($user->isVerified()) {
            return $this->json(['success' => false, 'message' => 'User already verified']);
        }

        $otp = $this->otpGenerator->generate();
        $accessToken = $this->tokens->signAccessToken($user);
        $verificationUrl = sprintf('%s/verify-otp?token=%s', $_ENV['FRONTEND_URL'] ?? '', $accessToken);

        // TODO: port ResendEmail template from utils/EmailTemplates.js (not seen)
        $this->mailer->send($user->getEmail(), 'Email Verification 🚌', '');

        $existingOtp = $this->otpRepository->findOneBy(['user' => $user]);
        if ($existingOtp !== null) {
            $existingOtp->setOtp($otp);
            $existingOtp->setExpiresAt(new \DateTimeImmutable('+30 minutes'));
        } else {
            $newOtp = new OTP();
            $newOtp->setUser($user);
            $newOtp->setOtp($otp);
            $newOtp->setExpiresAt(new \DateTimeImmutable('+30 minutes'));
            $this->em->persist($newOtp);
        }
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'OTP resent successfully']);
    }

    #[Route('/autoComplete', name: 'user_places_autocomplete', methods: ['GET'])]
    public function autoComplete(Request $request): JsonResponse
    {
        return $this->notImplemented('UserController.js::placesApi');
    }

    #[Route('/google', name: 'user_google_start', methods: ['GET'])]
    public function googleStart(): JsonResponse
    {
        // Was: passport.authenticate("google", {scope}) - replace with
        // knpuniversity/oauth2-client-bundle's
        // ClientRegistry::getClient('google')->redirect(['profile','email']).
        return $this->notImplemented('passport.js Google OAuth redirect');
    }

    #[Route('/google/callback', name: 'user_google_callback', methods: ['GET', 'POST'])]
    public function googleCallback(Request $request): JsonResponse
    {
        // PORTED PARTIALLY - the Node source was cut off mid-function
        // (ends at "// 📜 Log notifica..."), so the tail (whatever came
        // after the push-notification block - likely the activity log
        // call and the actual redirect response) is NOT ported.
        //
        // ALSO FLAGGING: Node registered this route as POST, but
        // passport-google-oauth20's standard redirect flow calls back via
        // GET (Google redirects the browser with a `code` query param).
        // A POST callback suggests the frontend might be doing its own
        // Google sign-in and posting the resulting profile/ID token to the
        // backend instead - which is a materially different flow from
        // what passport.authenticate("google", ...) as middleware implies.
        // Confirm which flow is intended before finishing this method;
        // registered both GET and POST here as a placeholder, not a decision.
        return $this->notImplemented('UserController.js::googleAuthCallback (source truncated + GET/POST mismatch, see comment)');
    }

    #[Route('/refresh-token', name: 'user_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        return $this->notImplemented('UserController.js::userTokenRefresh (not in source provided)');
    }

    /**
     * Was: validator.isStrongPassword(password) with default options
     * (minLength: 8, minLowercase: 1, minUppercase: 1, minNumbers: 1, minSymbols: 1).
     */
    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
    }
}
