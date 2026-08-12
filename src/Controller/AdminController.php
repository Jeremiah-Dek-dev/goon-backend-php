<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\AdminProfile;
use App\Entity\AdminInvite;
use App\Service\AdminService;
use App\Service\TokenService;
use App\Service\CookieService;
use App\Service\RefreshTokenService;
use App\Service\CaptchaService;
use App\Service\VerificationSessionService;
use App\Service\ActivityLogService;
use App\Service\FileUploadService;
use App\Enum\UserRole;
use App\Repository\BookingRepository;
use App\Repository\CommissionConfigRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use App\Repository\AdminProfileRepository;
use App\Repository\AdminInviteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Cookie;
use App\Entity\ActivityLog;
use App\Service\EmailService;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    use NotImplementedTrait;

    private const COOKIE_DOMAIN = null;

    public function __construct(

        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminService $adminService,

        private readonly AdminInviteRepository $adminInviteRepository,

        private readonly TwoFactorService $twoFactorService,

        private readonly AdminProfileRepository $adminProfileRepository,

        private readonly TokenService $tokenService,

        private readonly RefreshTokenService $refreshTokenService,

        private readonly CookieService $cookieService,

        private readonly VerificationSessionService $verificationSessionService,

        private readonly UserRepository $userRepository,

        private readonly EmailService $emailService,

        private readonly CaptchaService $captchaService,

        private readonly ActivityLogService $activityLogService,

        private readonly ActivityLogRepository $activityLogRepository,

        private readonly BookingRepository $bookingRepository,

        private readonly EntityManagerInterface $entityManager,

        private readonly string $frontendUrl,

        private readonly FileUploadService $fileUploadService,

        private readonly CommissionConfigRepository $commissionConfigRepository,

        #[Autowire(service: 'limiter.admin_login')]
        private readonly RateLimiterFactory $adminLoginLimiter,

        #[Autowire(service: 'limiter.captcha')]
        private readonly RateLimiterFactory $captchaLimiter,


    ) {}






#[Route('/login', name: 'admin_login', methods: ['POST'])]
public function login(
    Request $request
): JsonResponse {

    try {

        $data = json_decode(
            $request->getContent(),
            true
        );

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON body.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim(
            (string) ($data['email'] ?? '')
        );

        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json([
                'success' => false,
                'message' => 'Email and password are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $admin = $this->adminService->login(
            $email,
            $password
        );

        $adminProfile = $admin->getAdminProfile();

        if (!$adminProfile) {
            throw new \RuntimeException(
                'Admin profile not found.'
            );
        }

        if (!$adminProfile->isIs2FAVerified()) {

            $verificationToken =
                $this->verificationSessionService
                    ->refresh($admin);

            $response = $this->json([
                'success' => true,
                'requires2FA' => true,
                'message' => 'Two-factor authentication is required.',
                'admin' => [
                    'id' => $admin->getId(),
                    'name' => $admin->getName(),
                    'email' => $admin->getEmail(),
                ],
            ], Response::HTTP_ACCEPTED);

            $this->cookieService->setVerificationToken(
                $response,
                $verificationToken
            );

            return $response;
        }

        $adminAccessToken =
            $this->tokenService
                ->signAccessToken($admin);

        $adminRefreshToken =
            $this->refreshTokenService
                ->create($admin);

        $response = $this->json([
            'success' => true,
            'requires2FA' => false,
            'message' => 'Login successful',
            'roles' => $admin->getRoles(),
            'admin' => [
                'id' => $admin->getId(),
                'name' => $admin->getName(),
                'email' => $admin->getEmail(),
                'verified' => $admin->isVerified(),
            ],
        ]);

            $this->activityLogService->log(
                user: $admin,
                action: 'ADMIN_LOGIN_SUCCESSFULLY',
                description: sprintf(
                    'Login successfully from %s.',
                    $admin->getEmail()
                )
            );

        $this->cookieService->setAdminAuthCookies(
            $response,
            $adminAccessToken,
            $adminRefreshToken
        );

        $this->emailService->sendWelcome($admin); 

        return $response;

    } catch (\Throwable $e) {

    error_log(
        sprintf(
            '[ADMIN LOGIN] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        )
    );

    return $this->json([
        'success' => false,
        'message' => 'Invalid email or password.',
    ], Response::HTTP_UNAUTHORIZED);
}
}




#[Route('/forgot-password', name: 'admin_forgot_password', methods: ['POST'])]
public function forgotPassword(Request $request): JsonResponse
{
    $data = json_decode(
        $request->getContent(),
        true
    );

    $email = strtolower(
        trim($data['email'] ?? '')
    );

    if ($email === '') {
        return $this->json([
            'message' => 'Email is required.',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {
        $result = $this->adminService
            ->requestPasswordReset($email);

        /*
         * Administrator exists.
         */
        if ($result['sent'] === true) {

            $resetLink = sprintf(
                '%s/admin/auth0/reset-password/%s',
                rtrim(
                    $this->getParameter('frontendUrl'),
                    '/'
                ),
                $result['token']
            );

            /*
             * Send password reset email.
             */
            $this->emailService->sendAdminPasswordReset(
                $result['user'],
                $resetLink,
                $result['expiresAt']
            );

            /*
             * Log successful password reset request.
             */
            $this->activityLogService->log(
                user: $result['user'],
                action: 'REQUEST_ADMIN_RESET_PASSWORD',
                description: sprintf(
                    'Admin password reset email sent to %s.',
                    $result['user']->getEmail()
                )
            );
        }

        /*
         * Always return the same response.
         *
         * This prevents email enumeration.
         */
        return $this->json([
            'message' =>
                'If an administrator account exists for that email, '
                . 'a password reset link has been sent.',
        ]);

    } catch (\Throwable $e) {

        /*
         * Record the failure.
         *
         * Logging itself must never cause the request to fail again.
         */
        try {

            $this->activityLogService->log(
                user: null,
                action: 'REQUEST_ADMIN_RESET_PASSWORD_FAILED',
                description: sprintf(
                    'Failed to process admin password reset request for %s. Error: %s',
                    $email,
                    $e->getMessage()
                )
            );

        } catch (\Throwable $logException) {
            // Do not mask the original exception with a logging failure.
        }

        return $this->json([
            'message' =>
                'Unable to process password reset request.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/reset-password/{token}', name: 'admin_reset_password', methods: ['POST'])]
public function resetPassword(
    string $token,
    Request $request
): JsonResponse {
    $data = json_decode(
        $request->getContent(),
        true
    );

    $newPassword = $data['newPassword'] ?? '';

    if ($newPassword === '') {
        return $this->json([
            'message' => 'New password is required.',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {

        $user = $this->adminService->resetPassword(
            $token,
            $newPassword
        );

        /*
         * Log successful reset here if your existing
         * ActivityLog architecture is available.
         */

        return $this->json([
            'message' => 'Password reset successful.',
        ]);

    } catch (\RuntimeException $e) {

        return $this->json([
            'message' => $e->getMessage(),
        ], Response::HTTP_BAD_REQUEST);

    } catch (\Throwable $e) {

        return $this->json([
            'message' => 'Unable to reset password.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}





#[Route('/profile/change-password', name: 'admin_change_password', methods: ['POST'])]
public function changePassword(
    Request $request,
    #[CurrentUser] ?User $admin
): JsonResponse {

    /*
     * Authentication check
     */
    if (!$admin) {
        return $this->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /*
     * Parse request body
     */
    $data = json_decode(
        $request->getContent(),
        true
    );

    if (!is_array($data)) {
        return $this->json([
            'success' => false,
            'message' => 'Invalid JSON body.',
        ], Response::HTTP_BAD_REQUEST);
    }

    $currentPassword = (string) (
        $data['current'] ?? ''
    );

    $newPassword = (string) (
        $data['newPassword'] ?? ''
    );

    $confirmPassword = (string) (
        $data['confirmPassword'] ?? ''
    );

    /*
     * Required fields
     */
    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {
        return $this->json([
            'success' => false,
            'message' => 'All fields are required.',
        ], Response::HTTP_BAD_REQUEST);
    }

    $strongPasswordRegex =
        '/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/';

    if (!preg_match(
        $strongPasswordRegex,
        $newPassword
    )) {
        return $this->json([
            'success' => false,
            'message' =>
                'Password must contain at least 8 characters, '
                . 'including uppercase, lowercase, number, '
                . 'and special character.',
        ], Response::HTTP_BAD_REQUEST);
    }

    /*
     * Confirm password
     */
    if ($newPassword !== $confirmPassword) {
        return $this->json([
            'success' => false,
            'message' =>
                'New password and confirmation do not match.',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {

        /*
         * Verify current password.
         */
        if (!$this->passwordHasher->isPasswordValid(
            $admin,
            $currentPassword
        )) {
            $this->activityLogService->log(
                user: $admin,
                action: 'ADMIN_PASSWORD_CHANGE_FAILED',
                description: sprintf(
                    'Failed password change attempt for %s: current password is incorrect.',
                    $admin->getEmail()
                )
            );

            return $this->json([
                'success' => false,
                'message' =>
                    'Current password is incorrect.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /*
         * Prevent reusing the current password.
         */
        if ($this->passwordHasher->isPasswordValid(
            $admin,
            $newPassword
        )) {
            return $this->json([
                'success' => false,
                'message' =>
                    'New password must be different from the old password.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->adminService->changePassword(
            $admin,
            $newPassword
        );

        /*
         * Activity log
         */
        $this->activityLogService->log(
            user: $admin,
            action: 'ADMIN_PASSWORD_CHANGED',
            description: sprintf(
                'Admin %s changed password successfully.',
                $admin->getEmail()
            )
        );

        return $this->json([
            'success' => true,
            'message' =>
                'Password changed successfully.',
        ]);

    } catch (\Throwable $e) {

        try {

            $this->activityLogService->log(
                user: $admin,
                action: 'ADMIN_PASSWORD_CHANGE_FAILED',
                description: sprintf(
                    'Failed to change password for %s. Error: %s',
                    $admin->getEmail(),
                    $e->getMessage()
                )
            );

        } catch (\Throwable $logException) {
            // Never mask the original exception.
        }

        return $this->json([
            'success' => false,
            'message' =>
                'Unable to change password.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}





#[Route('/profile/avatar', name: 'admin_update_avatar', methods: ['POST'])]
public function updateAdminAvatar(
    Request $request,
    #[CurrentUser] ?User $admin
): JsonResponse {

    /*
     * Authentication
     */
    if (!$admin) {
        return $this->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /*
     * Ensure a file was uploaded.
     */
    $file = $request->files->get('avatar');

    if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
        return $this->json([
            'success' => false,
            'message' => 'Avatar file is required.',
        ], Response::HTTP_BAD_REQUEST);
    }

    $oldAvatar = $admin->getAvatar();

    try {

        $newAvatar = $this->fileUploadService
            ->uploadAvatar($file);

        /*
         * Update entity.
         */
        $admin->setAvatar($newAvatar);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        /*
         * Delete old local avatar only after the
         * database has successfully been updated.
         */
        if (
            $oldAvatar &&
            $oldAvatar !== $newAvatar
        ) {
            $this->fileUploadService
                ->deleteAvatar($oldAvatar);
        }

        /*
         * Activity log.
         */
        $this->activityLogService->log(
            user: $admin,
            action: 'ADMIN_AVATAR_UPDATED',
            description: sprintf(
                '%s updated their avatar.',
                $admin->getName()
                    ?: $admin->getEmail()
            )
        );

        return $this->json([
            'success' => true,
            'message' => 'Avatar updated successfully.',
            'user' => [
                'avatar' => $admin->getAvatar(),
            ],
        ]);

    } catch (\InvalidArgumentException $e) {

        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_BAD_REQUEST);

    } catch (\Throwable $e) {

        /*
         * Log unexpected failure.
         */
        try {

            $this->activityLogService->log(
                user: $admin,
                action: 'ADMIN_AVATAR_UPDATE_FAILED',
                description: sprintf(
                    'Failed attempt to update avatar for %s. Error: %s',
                    $admin->getEmail(),
                    $e->getMessage()
                )
            );

        } catch (\Throwable $logException) {
            // Never mask the original exception.
        }

        return $this->json([
            'success' => false,
            'message' => 'Failed to update avatar.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}



    /**
     * CURRENT ADMIN
     *
     * Node:
     * get admin profile
     */
/**
 * CURRENT ADMIN
 */
#[Route('/me', name: 'admin_me', methods: ['GET'])]
public function me(
    #[CurrentUser] ?User $admin
): JsonResponse {

    if (!$admin) {

        return $this->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);

    }


    $adminProfile = $admin->getAdminProfile();

    return $this->json([

        'success' => true,

        'admin' => [

            'id' => $admin->getId(),

            'name' => $admin->getName(),

            'email' => $admin->getEmail(),

            'avatar' => $admin->getAvatar()
                ? $this->generateUrl(
                    'admin_avatar',
                    [
                        'filename' => basename($admin->getAvatar()),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
                : null,

            'verified' => $admin->isVerified(),

            'roles' => $admin->getRoles(),

            'twoFactorEnabled' =>
                $adminProfile
                ? $adminProfile->isIs2FAVerified()
                : false,

            'disabled' =>
                $adminProfile
                ? $adminProfile->isDisabled()
                : false,

            'lastLoginAt' =>
                $admin->getLastLoginAt()?->format(DATE_ATOM),

            'lastActiveAt' =>
                $admin->getLastActiveAt()?->format(DATE_ATOM),

        ]

    ]);

}


#[Route('/avatar/{filename}', name: 'admin_avatar', methods: ['GET'])]
public function avatar(string $filename): BinaryFileResponse
{
    /*
     * Prevent path traversal.
     */
    if (
        $filename === '' ||
        $filename !== basename($filename)
    ) {
        throw $this->createNotFoundException();
    }

    $avatarPath = sprintf(
        '%s/public/uploads/avatars/%s',
        $this->getParameter('kernel.project_dir'),
        $filename
    );

    if (!is_file($avatarPath)) {
        throw $this->createNotFoundException(
            'Avatar not found.'
        );
    }

    return new BinaryFileResponse(
        new File($avatarPath)
    );
}




#[Route('/profile/delete', name: 'admin_delete_profile', methods: ['DELETE'])]
public function deleteAdminProfile(
    #[CurrentUser] ?User $admin
): JsonResponse {

    /*
     * Authentication
     */
    if (!$admin) {
        return $this->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    try {

        /*
         * Remove the admin profile.
         */
        $adminProfile = $admin->getAdminProfile();

        if ($adminProfile) {
            $this->entityManager->remove(
                $adminProfile
            );
        }

        /*
         * Remove administrator roles.
         *
         * Keep the User entity itself.
         */
        foreach ($admin->getRoleAssignments() as $assignment) {

            $role = $assignment->getRole();

            if (in_array(
                $role,
                [
                    UserRole::ADMIN,
                    UserRole::SUPER_ADMIN,
                    UserRole::ADMIN_MANAGER,
                ],
                true
            )) {
                $this->entityManager->remove(
                    $assignment
                );
            }
        }

        /*
         * Keep the user account but update its
         * modification timestamp.
         */
        $admin->setUpdatedAt(
            new \DateTimeImmutable()
        );

        $this->entityManager->persist($admin);

        /*
         * Commit everything together.
         */
        $this->entityManager->flush();

        /*
         * Activity log.
         */
        $this->activityLogService->log(
            user: $admin,
            action: 'ADMIN_PROFILE_DELETED',
            description: sprintf(
                'Admin profile deleted and administrative roles removed for %s.',
                $admin->getEmail()
            )
        );

        return $this->json([
            'success' => true,
            'message' =>
                'Admin profile deleted successfully.',
        ]);

    } catch (\Throwable $e) {

        try {

            $this->activityLogService->log(
                user: $admin,
                action: 'ADMIN_PROFILE_DELETE_FAILED',
                description: sprintf(
                    'Failed to delete admin profile for %s. Error: %s',
                    $admin->getEmail(),
                    $e->getMessage()
                )
            );

        } catch (\Throwable $logException) {
            // Never mask the original exception.
        }

        return $this->json([
            'success' => false,
            'message' =>
                'Failed to delete admin profile.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}






    /**
     * REFRESH TOKEN
     */
/**
 * REFRESH TOKEN
 */
#[Route('/refresh-token', name: 'admin_refresh_token', methods:['POST'])]
public function refreshToken(
    Request $request
): JsonResponse {

    $rawToken = $request
        ->cookies
        ->get('adminRefreshToken');

    if (!$rawToken) {

        return $this->json([
            'success' => false,
            'message' => 'Refresh token missing'
        ], 401);

    }

    $storedToken =
        $this->refreshTokenService
            ->validate($rawToken);

    if (!$storedToken) {

        return $this->json([
            'success' => false,
            'message' => 'Invalid refresh token'
        ], 401);

    }

    $admin = $storedToken->getUser();

    $newRefreshToken =
        $this->refreshTokenService
            ->rotate($storedToken);

    $newAccessToken =
        $this->tokenService
            ->signAccessToken($admin);

    $response = $this->json([

        'success' => true,

        'message' => 'Token refreshed'

    ]);

    $this->cookieService->setAdminAuthCookies(
        $response,
        $newAccessToken,
        $newRefreshToken
    );

    return $response;
}




    /**
     * LOGOUT
     */
/**
 * LOGOUT
 */
#[Route('/logout', name: 'admin_logout', methods:['POST'])]
public function logout(): JsonResponse
{
    $response = new JsonResponse([

        'success' => true,

        'message' => 'Logged out successfully'

    ]);

    $this->cookieService->clearAdminAuthCookies(
        $response
    );

    return $response;
}





    /**
 * VERIFY ADMIN CAPTCHA HOLD
 */
#[Route(
    '/verify-captcha-hold',
    name: 'admin_verify_captcha_hold',
    methods: ['POST']
)]
    public function verifyCaptchaHold(
        Request $request,
        #[CurrentUser] ?User $admin
    ): JsonResponse {

        try {

            $data = json_decode(
                $request->getContent(),
                true
            );

            if (
                !isset($data['startedAt'])
            ) {

                return $this->json([

                    'success' => false,

                    'message' => 'startedAt is required.'

                ], 400);

            }

            /**
             * Validate hold duration
             */
            $this->captchaService->verifyHold(
                (int) $data['startedAt']
            );

            /**
             * Generate captcha JWT
             */
            $captchaToken =
                $this->captchaService
                    ->generateCaptchaToken(
                        $request
                    );

            $response = $this->json([

                'success' => true,

                'message' => 'CAPTCHA verified.'

            ]);

            /**
             * Store HttpOnly cookie
             */
            $this->cookieService
                ->setAdminCaptchaCookie(
                    $response,
                    $captchaToken
                );

            /**
             * Activity log
             */
            $this->activityLogService->log(
                user: $admin,
                action: 'ADMIN_CAPTCHA_VERIFIED',
                description: $admin
                    ? sprintf(
                        'Admin %s passed CAPTCHA verification.',
                        $admin->getEmail()
                    )
                    : sprintf(
                        'Anonymous client (%s) passed CAPTCHA verification.',
                        $request->getClientIp()
                    )
            );

            return $response;

        } catch (\Throwable $e) {

            $this->activityLogService->log(

                user: $admin,

                action: 'ADMIN_CAPTCHA_FAILED',

                description: $admin
                    ? sprintf(
                        'Admin %s failed CAPTCHA verification. %s',
                        $admin->getEmail(),
                        $e->getMessage()
                    )
                    : sprintf(
                        'Anonymous CAPTCHA verification failed. %s',
                        $e->getMessage()
                    )
            );

            return $this->json([

                'success' => false,

                'message' => $e->getMessage()

            ], 400);

        }

    }





#[Route('/verify-2fa', name: 'admin_verify_2fa', methods: ['POST'])]
public function verify2FA(
    Request $request
): JsonResponse {
    $data = json_decode(
        $request->getContent(),
        true
    );

    if (!is_array($data)) {
        return $this->json([
            'success' => false,
            'message' => 'Invalid JSON body.',
        ], Response::HTTP_BAD_REQUEST);
    }

    $totpCode = trim(
        (string) ($data['totpCode'] ?? '')
    );

    if ($totpCode === '') {
        return $this->json([
            'success' => false,
            'message' => 'Missing TOTP code.',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {

        $verificationToken =
            $this->cookieService
                ->getVerificationToken($request);

        if (!$verificationToken) {
            return $this->json([
                'success' => false,
                'message' => '2FA verification session not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user =
            $this->verificationSessionService
                ->findUserByToken($verificationToken);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => '2FA verification session expired.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $admin =
            $this->adminProfileRepository
                ->findOneBy([
                    'user' => $user,
                ]);

        if (!$admin) {
            return $this->json([
                'success' => false,
                'message' => 'Admin profile not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($admin->isDisabled()) {
            return $this->json([
                'success' => false,
                'message' => 'Account disabled.',
            ], Response::HTTP_FORBIDDEN);
        }

        $lockUntil = $admin->getLockUntil();

        if (
            $lockUntil !== null &&
            $lockUntil > new \DateTimeImmutable()
        ) {
            return $this->json([
                'success' => false,
                'message' => sprintf(
                    'Account locked until %s.',
                    $lockUntil->format('Y-m-d H:i:s')
                ),
            ], Response::HTTP_LOCKED);
        }

        $secret = $admin->getTwoFASecret();

        if (!$secret) {
            return $this->json([
                'success' => false,
                'message' => '2FA not set up for this account.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $verified =
            $this->twoFactorService
                ->verifyCode(
                    $secret,
                    $totpCode
                );

        if (!$verified) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid 2FA code.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $admin->setIs2FAVerified(true);

        $admin
            ->setFailedLoginAttempts(0)
            ->setLockUntil(null);

        $this->entityManager->flush();

        $accessToken =
            $this->tokenService
                ->signAccessToken($user);

        $refreshToken =
            $this->refreshTokenService
                ->create($user);

        $response = $this->json([
            'success' => true,
            'message' => '2FA verification successful.',
            'roles' => $user->getRoles(),
            'admin' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'verified' => $user->isVerified(),
            ],
        ]);

        $this->cookieService
            ->setAdminAuthCookies(
                $response,
                $accessToken,
                $refreshToken
            );

        $this->cookieService
            ->clearVerificationToken($response);

        $this->verificationSessionService
            ->delete($verificationToken);

        $this->activityLogService->log(
            $user,
            'VERIFY_2FA',
            sprintf(
                'Admin 2FA verified for %s',
                $user->getEmail()
            )
        );

        return $response;

    } catch (\Throwable $e) {

    $this->activityLogService->log(
        null,
        'VERIFY_2FA_FAILED',
        sprintf(
            'Error during admin 2FA verification: %s',
            $e->getMessage()
        )
    );

    return $this->json([
        'success' => false,
        'message' => $e->getMessage(),
        'exception' => $e::class,
    ], Response::HTTP_INTERNAL_SERVER_ERROR);
}
}


#[Route('/setup-2fa', name: 'admin_setup_2fa', methods: ['GET'])]
public function setup2FA(
    Request $request
): JsonResponse {
    try {
        $verificationToken =
            $this->cookieService->getVerificationToken($request);

        if (!$verificationToken) {
            return $this->json([
                'success' => false,
                'message' => '2FA verification session not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user =
            $this->verificationSessionService
                ->findUserByToken($verificationToken);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => '2FA verification session expired.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $admin =
            $this->adminProfileRepository->findOneBy([
                'user' => $user,
            ]);

        if (!$admin) {
            return $this->json([
                'success' => false,
                'message' => 'Admin profile not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($admin->isDisabled()) {
            return $this->json([
                'success' => false,
                'message' => 'Account disabled.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$admin->getTwoFASecret()) {
            $secret =
                $this->twoFactorService->generateSecret();

            $admin->setTwoFASecret($secret);

            $this->entityManager->flush();
        }

        $provisioningUri =
            $this->twoFactorService->getProvisioningUri(
                $admin->getTwoFASecret(),
                $user->getEmail()
            );

        $qrDataUrl =
            $this->twoFactorService->generateQrDataUrl(
                $provisioningUri
            );

        $backupCodes = [];
        $backupCodesGenerated = false;

        if ($admin->getBackupCodes()->isEmpty()) {

            $result =
                $this->twoFactorService
                    ->generateBackupCodes($admin);

            foreach ($result['codes'] as $backupCode) {
                $this->entityManager->persist($backupCode);
            }

            $admin->setBackupCodesGeneratedAt(
                new \DateTimeImmutable()
            );

            $this->entityManager->flush();

            $backupCodes = $result['plainCodes'];
            $backupCodesGenerated = true;

            $this->activityLogService->log(
                $user,
                'SETUP_2FA',
                sprintf(
                    'Generated 2FA setup for admin %s',
                    $user->getEmail()
                )
            );
        }

        return $this->json([
            'success' => true,
            'qrDataURL' => $qrDataUrl,
            'backupCodes' => $backupCodes,
            'backupCodesGenerated' => $backupCodesGenerated,
            'backupCodesAlreadyExist' =>
                !$admin->getBackupCodes()->isEmpty(),
        ]);

    } catch (\Throwable $e) {

    $this->activityLogService->log(
        null,
        'SETUP_2FA_FAILED',
        sprintf(
            'Error generating admin 2FA setup: %s',
            $e->getMessage()
        )
    );

    throw $e;
}
}


#[Route('/verify-backup-code', name: 'admin_verify_backup_code', methods: ['POST'])]
public function verifyBackupCode(
    Request $request
): JsonResponse {
    $data = json_decode(
        $request->getContent(),
        true
    );

    if (!is_array($data)) {
        return $this->json([
            'success' => false,
            'message' => 'Invalid JSON body.',
        ], Response::HTTP_BAD_REQUEST);
    }

    $backupCode = trim(
        (string) ($data['backupCode'] ?? '')
    );

    if ($backupCode === '') {
        return $this->json([
            'success' => false,
            'message' => 'Missing backup code.',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {

        /*
         * Get the temporary pre-2FA verification token.
         */
        $verificationToken =
            $this->cookieService
                ->getVerificationToken($request);

        if (!$verificationToken) {
            return $this->json([
                'success' => false,
                'message' => '2FA verification session not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /*
         * Resolve the user from the temporary session.
         */
        $user =
            $this->verificationSessionService
                ->findUserByToken($verificationToken);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => '2FA verification session expired.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /*
         * Find admin profile.
         */
        $admin =
            $this->adminProfileRepository
                ->findOneBy([
                    'user' => $user,
                ]);

        if (!$admin) {
            return $this->json([
                'success' => false,
                'message' => 'Admin profile not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        /*
         * Account status.
         */
        if ($admin->isDisabled()) {
            return $this->json([
                'success' => false,
                'message' => 'Account disabled.',
            ], Response::HTTP_FORBIDDEN);
        }

        $lockUntil = $admin->getLockUntil();

        if (
            $lockUntil !== null &&
            $lockUntil > new \DateTimeImmutable()
        ) {
            return $this->json([
                'success' => false,
                'message' => sprintf(
                    'Account locked until %s.',
                    $lockUntil->format('Y-m-d H:i:s')
                ),
            ], Response::HTTP_LOCKED);
        }

        /*
         * Make sure backup codes exist.
         */
        if ($admin->getBackupCodes()->isEmpty()) {
            return $this->json([
                'success' => false,
                'message' => 'No backup codes set up.',
            ], Response::HTTP_BAD_REQUEST);
        }

        /*
         * Verify and consume the backup code.
         *
         * consumeBackupCode() marks the matching
         * code as used and persists the change.
         */
        $valid =
            $this->twoFactorService
                ->consumeBackupCode(
                    $admin,
                    $backupCode
                );

        if (!$valid) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid or already used backup code.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /*
         * Mark 2FA as verified.
         */
        if (!$admin->isIs2FAVerified()) {
            $admin->setIs2FAVerified(true);
        }

        /*
         * Reset login failure state.
         */
        $admin
            ->setFailedLoginAttempts(0)
            ->setLockUntil(null);

        $this->entityManager->flush();

        /*
         * Issue real admin authentication tokens.
         */
        $accessToken =
            $this->tokenService
                ->signAccessToken($user);

        $refreshToken =
            $this->refreshTokenService
                ->create($user);

        /*
         * Build response.
         */
        $response = $this->json([
            'success' => true,
            'message' => 'Backup code verification successful.',
            'roles' => $user->getRoles(),
            'admin' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'verified' => $user->isVerified(),
            ],
        ]);

        /*
         * Replace temporary authentication
         * with real admin authentication.
         */
        $this->cookieService
            ->setAdminAuthCookies(
                $response,
                $accessToken,
                $refreshToken
            );

        /*
         * Remove temporary 2FA session.
         */
        $this->cookieService
            ->clearVerificationToken($response);

        $this->verificationSessionService
            ->delete($verificationToken);

        /*
         * Audit log.
         */
        $this->activityLogService->log(
            $user,
            'VERIFY_BACKUP_CODE',
            sprintf(
                'Admin backup code verified for %s',
                $user->getEmail()
            )
        );

        return $response;

    } catch (\Throwable $e) {

        /*
         * Log the real exception server-side.
         */
        $this->activityLogService->log(
            null,
            'VERIFY_BACKUP_CODE_FAILED',
            sprintf(
                'Error during admin backup code verification: %s',
                $e->getMessage()
            )
        );

        return $this->json([
            'success' => false,
            'message' => 'Unable to verify backup code.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}



#[Route(
    '/regenerate-backup-codes',
    name: 'admin_regenerate_backup_codes',
    methods: ['POST']
)]
public function regenerateBackupCodes(
    #[CurrentUser] ?User $user
): JsonResponse {
    try {

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Admin not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $admin =
            $this->adminProfileRepository->findOneBy([
                'user' => $user,
            ]);

        if (!$admin) {
            return $this->json([
                'success' => false,
                'message' => 'Admin profile not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $result =
            $this->twoFactorService->regenerateBackupCodes(
                $admin
            );

        $this->activityLogService->log(
            $user,
            'REGENERATE_BACKUP_CODES',
            sprintf(
                'Regenerated backup codes for admin %s',
                $user->getEmail()
            )
        );

        return $this->json([
            'success' => true,
            'backupCodes' => $result['plainCodes'],
        ]);

    } catch (\Throwable $e) {

        return $this->json([
            'success' => false,
            'message' => 'Server error.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




    #[Route('/csrf-token', name: 'admin_csrf_token', methods: ['GET'])]
    public function csrfToken(Request $request): JsonResponse
    {
        // Symfony's CSRF is generated via the injected CsrfTokenManagerInterface,
        // not a custom middleware - straightforward once wired up.
        return $this->notImplemented('csrf.js::generateCsrfToken');
    }




#[Route('/stats', name: 'admin_dashboard_stats', methods: ['GET'])]
public function dashboardStats(): JsonResponse
{
    try {
        $stats = $this->adminService->getDashboardStats();

        return $this->json([
            'success' => true,
            'stats' => $stats,
        ]);

    } catch (\Throwable $e) {
        return $this->json([
            'success' => false,
            'message' => 'Server error while fetching dashboard stats.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/invite', name: 'admin_invite', methods: ['POST'])]
public function inviteAdmin(
    Request $request,
    #[CurrentUser] ?User $user
): JsonResponse {
    try {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON body.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $roles = $data['roles'] ?? ['ADMIN'];
        $name = $data['name'] ?? null;

        if ($email === '') {
            return $this->json([
                'success' => false,
                'message' => 'Email required',
            ], Response::HTTP_BAD_REQUEST);
        }

        /*
         * Normalize roles exactly like the Node implementation.
         */
        if (!is_array($roles) || count($roles) === 0) {
            $roles = ['ADMIN'];
        }

        $roles = array_values(array_unique(
            array_map(
                static fn ($role) => strtoupper(trim((string) $role)),
                $roles
            )
        ));

        /*
         * Remove previous unaccepted invitations for this email.
         */
        $existingInvites = $this->adminInviteRepository->findBy([
            'email' => $email,
            'accepted' => false,
        ]);

        foreach ($existingInvites as $existingInvite) {
            $this->entityManager->remove($existingInvite);
        }

        /*
         * Generate a cryptographically secure 64-character token.
         * Equivalent to crypto.randomBytes(32).toString("hex").
         */
        $token = bin2hex(random_bytes(32));

        $expiresAt = new \DateTimeImmutable('+24 hours');

        /*
         * Prisma stored roles as JSON inside a String.
         * Preserve that representation in the PHP entity.
         */
        $invite = new AdminInvite();
        $invite
            ->setEmail($email)
            ->setRoles(json_encode($roles, JSON_THROW_ON_ERROR))
            ->setToken($token)
            ->setExpiresAt($expiresAt)
            ->setAccepted(false)
            ->setCreatedBy($user);

        $this->entityManager->persist($invite);
        $this->entityManager->flush();

        /*
         * Build frontend invitation URL.
         */
        $inviteLink = sprintf(
                '%s/admin/auth0/accept-invite?token=%s',
                rtrim($this->frontendUrl, '/'),
                urlencode($token)
            );

        $formattedExpiry = $expiresAt
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('F j, Y, h:i A');

        /*
         * Send invitation email.
         *
         * We will add sendAdminInvite() to EmailService next.
         */
        $this->emailService->sendAdminInvite(
            $email,
            $name,
            $inviteLink,
            $formattedExpiry
        );

        /*
         * Activity log.
         */
        $rolesString = implode(', ', $roles);

        $this->activityLogService->log(
            $user,
            'Invite Admin',
            sprintf(
                'Invited admin with email: %s',
                $email
            )
        );

        return $this->json([
            'success' => true,
            'message' => 'Invite sent successfully',
        ]);

    } catch (\Throwable $e) {

        $this->activityLogService->log(
            $user,
            'Invite Admin Failed',
            sprintf(
                'Failed to invite admin with email: %s. Error: %s',
                $email ?? 'unknown',
                $e->getMessage()
            )
        );

        return $this->json([
            'success' => false,
            'message' => $e->getMessage() ?: 'Failed to send invite',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}



#[Route('/invite/{id}', name: 'admin_cancel_invite', methods: ['DELETE'])]
public function cancelInvite(
    int $id,
    #[CurrentUser] ?User $user
): JsonResponse {
    try {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $invite = $this->adminInviteRepository->findOneBy([
            'id' => $id,
            'accepted' => false,
            'createdBy' => $user,
        ]);

        if (!$invite) {
            return $this->json([
                'success' => false,
                'message' => 'Invite not found or not authorized',
            ], Response::HTTP_NOT_FOUND);
        }

        $email = $invite->getEmail();

        $this->entityManager->remove($invite);
        $this->entityManager->flush();

        $this->activityLogService->log(
            $user,
            'CANCEL_INVITE',
            sprintf(
                'Cancelled invite for email: %s',
                $email
            )
        );

        return $this->json([
            'success' => true,
            'message' => 'Invite cancelled',
        ]);

    } catch (\Throwable $e) {
        $this->activityLogService->log(
            $user,
            'CANCEL_INVITE_FAILED',
            sprintf(
                'Failed to cancel invite for id: %d. Error: %s',
                $id,
                $e->getMessage()
            )
        );

        return $this->json([
            'success' => false,
            'message' => 'Failed to cancel invite',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/accept-invite', name: 'admin_accept_invite', methods: ['POST'])]
public function acceptInvite(
    Request $request
): JsonResponse {
    $data = json_decode($request->getContent(), true);

    if (!is_array($data)) {
        return $this->json([
            'success' => false,
            'message' => 'Invalid JSON body.',
        ], Response::HTTP_BAD_REQUEST);
    }

    $token = trim((string) ($data['token'] ?? ''));
    $name = trim((string) ($data['name'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($token === '' || $name === '' || $password === '') {
        return $this->json([
            'success' => false,
            'message' => 'Missing required fields',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {
        $invite = $this->adminInviteRepository->findOneBy([
            'token' => $token,
            'accepted' => false,
        ]);

        if (
            !$invite ||
            $invite->getExpiresAt() < new \DateTimeImmutable()
        ) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid or expired invite',
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim($invite->getEmail()));

        /*
         * Prevent duplicate accounts.
         */
        $existingUser = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'User already exists',
            ], Response::HTTP_CONFLICT);
        }

        /*
         * Decode invited roles.
         */
        $roles = json_decode($invite->getRoles(), true);

        if (!is_array($roles) || count($roles) === 0) {
            $roles = [UserRole::ADMIN->value];
        }

        /*
         * Normalize and validate roles.
         */
        $normalizedRoles = [];

        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));

            try {
                $normalizedRoles[] = UserRole::from($role);
            } catch (\ValueError) {
                throw new \RuntimeException(
                    sprintf('Invalid invited role: %s', $role)
                );
            }
        }

        /*
         * Create everything atomically.
         */
        $this->entityManager->beginTransaction();

        try {
            $user = new User();

            $user->setName($name);
            $user->setEmail($email);

            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $password
                )
            );

            // Invited admin accounts are verified automatically.
            $user->setVerified(true);

            foreach ($normalizedRoles as $role) {
                $user->addRole($role);
            }

            $adminProfile = new AdminProfile();
            $adminProfile->setUser($user);

            $this->entityManager->persist($user);
            $this->entityManager->persist($adminProfile);

            $invite->setAccepted(true);

            $this->entityManager->persist($invite);

            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }

        /*
         * Log successful acceptance.
         */
        $roleNames = array_map(
            static fn (UserRole $role) => $role->value,
            $normalizedRoles
        );

        $this->activityLogService->log(
            $user,
            'ACCEPT_INVITE',
            sprintf(
                'New admin account created for %s',
                $email
            )
        );

        return $this->json([
            'success' => true,
            'message' => 'Account created successfully. Please log in to set up 2FA.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ],
        ]);

    } catch (\Throwable $e) {
        $this->activityLogService->log(
            null,
            'ACCEPT_INVITE_FAILED',
            sprintf(
                'Failed invite acceptance for token: %s. Error: %s',
                $token,
                $e->getMessage()
            )
        );

        return $this->json([
            'success' => false,
            'message' => $e->getMessage()
                ?: 'Server error while accepting invite',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/pending-invites', name: 'admin_pending_invites', methods: ['GET'])]
public function pendingInvites(
    #[CurrentUser] ?User $user
): JsonResponse {
    try {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $invites = $this->adminInviteRepository->findBy(
            [
                'accepted' => false,
                'createdBy' => $user,
            ],
            [
                'createdAt' => 'DESC',
            ]
        );

        $formattedInvites = array_map(
            static function (AdminInvite $invite): array {
                $roles = json_decode($invite->getRoles(), true);

                return [
                    'id' => $invite->getId(),
                    'email' => $invite->getEmail(),
                    'roles' => is_array($roles) ? $roles : [],
                    'expiresAt' => $invite->getExpiresAt()->format(DATE_ATOM),
                    'createdAt' => $invite->getCreatedAt()->format(DATE_ATOM),
                ];
            },
            $invites
        );

        $this->activityLogService->log(
            $user,
            'LIST_PENDING_INVITES',
            'Fetched pending invites'
        );

        return $this->json([
            'success' => true,
            'invites' => $formattedInvites,
        ]);

    } catch (\Throwable $e) {
        $this->activityLogService->log(
            $user,
            'LIST_PENDING_INVITES_FAILED',
            'Failed to fetch pending invites. Error: ' . $e->getMessage()
        );

        return $this->json([
            'success' => false,
            'message' => 'Unable to fetch invites',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/monthly-revenue', name: 'admin_monthly_revenue', methods: ['GET'])]
public function monthlyRevenue(): JsonResponse
{
    try {
        $data = $this->adminService->getMonthlyRevenue();

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);

    } catch (\Throwable $e) {
        return $this->json([
            'success' => false,
            'message' => 'Server error while fetching monthly revenue.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/booking-status', name: 'admin_booking_status', methods: ['GET'])]
public function bookingStatusDistribution(): JsonResponse
{
    try {
        $data = $this->adminService->getBookingStatusDistribution();

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);

    } catch (\Throwable $e) {
        return $this->json([
            'success' => false,
            'message' => 'Server error while fetching status distribution.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/monthly-bookings', name: 'admin_monthly_bookings', methods: ['GET'])]
public function monthlyBookings(): JsonResponse
{
    try {
        $data = $this->adminService->getMonthlyBookings();

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);

    } catch (\Throwable $e) {
        return $this->json([
            'success' => false,
            'message' => 'Server error while fetching monthly bookings.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




#[Route('/commission', name: 'admin_get_commission_rate', methods: ['GET'])]
public function getCommissionRate(
    #[CurrentUser] ?User $user
): JsonResponse {
    try {

        $rate = $this->adminService->getCommissionRate();

        $this->activityLogService->log(
            $user,
            'GET_COMMISSION_RATE',
            sprintf(
                'Fetched current commission rate of %s%%',
                $rate * 100
            )
        );

        return $this->json([
            'success' => true,
            'rate' => $rate,
        ]);

    } catch (\Throwable $e) {

        $this->activityLogService->log(
            $user,
            'GET_COMMISSION_RATE_FAILED',
            'Failed to fetch commission rate: ' . $e->getMessage()
        );

        return $this->json([
            'success' => false,
            'message' => 'Server error',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}



#[Route('/commission', name: 'admin_set_commission_rate', methods: ['POST'])]
public function setCommissionRate(
    Request $request,
    #[CurrentUser] ?User $user
): JsonResponse {
    try {

        $data = json_decode(
            $request->getContent(),
            true
        );

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON body.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $rate = $data['rate'] ?? null;

        if ($rate === null || !is_numeric($rate)) {
            return $this->json([
                'success' => false,
                'message' => 'Rate must be between 0 and 1',
            ], Response::HTTP_BAD_REQUEST);
        }

        $rate = (float) $rate;

        if ($rate < 0 || $rate > 1) {
            return $this->json([
                'success' => false,
                'message' => 'Rate must be between 0 and 1',
            ], Response::HTTP_BAD_REQUEST);
        }

        /*
         * Optional effectiveFrom.
         */
        $effectiveFrom = null;

        if (
            isset($data['effectiveFrom']) &&
            $data['effectiveFrom'] !== ''
        ) {
            try {

                $effectiveFrom = new \DateTimeImmutable(
                    $data['effectiveFrom']
                );

            } catch (\Throwable) {

                return $this->json([
                    'success' => false,
                    'message' => 'Invalid effectiveFrom date.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $config = $this->adminService->setCommissionRate(
            $rate,
            $effectiveFrom
        );

        $this->activityLogService->log(
            $user,
            'SET_COMMISSION_RATE',
            sprintf(
                'Commission rate updated to %s%%',
                $rate * 100
            )
        );

        return $this->json([
            'success' => true,
            'config' => [
                'id' => $config->getId(),
                'rate' => $config->getRate(),
                'effectiveFrom' => $config
                    ->getEffectiveFrom()
                    ->format(\DateTimeInterface::ATOM),
                'active' => $config->isActive(),
                'createdAt' => $config
                    ->getCreatedAt()
                    ->format(\DateTimeInterface::ATOM),
                'updatedAt' => $config
                    ->getUpdatedAt()
                    ->format(\DateTimeInterface::ATOM),
            ],
        ]);

    } catch (\InvalidArgumentException $e) {

        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_BAD_REQUEST);

    } catch (\Throwable $e) {

        $this->activityLogService->log(
            $user,
            'SET_COMMISSION_RATE_FAILED',
            'Failed to update commission rate: ' . $e->getMessage()
        );

        return $this->json([
            'success' => false,
            'message' => 'Server error',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}


    // ── Push notifications ──────────────────────────────────────────────
    #[Route('/global-update', name: 'admin_global_update', methods: ['POST'])]
    public function globalUpdate(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::publishGlobalUpdate');
    }

    #[Route('/push-to-drivers', name: 'admin_push_to_drivers', methods: ['POST'])]
    public function pushToDrivers(Request $request): JsonResponse
    {
        // No handler existed in the Node route - needs a real implementation,
        // not a port. Flagged in PHASE3_NOTES.md.
        return $this->notImplemented('AdminController.js::(push-to-drivers - no Node handler existed)');
    }

    #[Route('/push-to-users', name: 'admin_push_to_users', methods: ['POST'])]
    public function pushToUsers(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::(push-to-users - no Node handler existed)');
    }

    #[Route('/push-to-customers', name: 'admin_push_to_customers', methods: ['POST'])]
    public function pushToCustomers(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::(push-to-customers - no Node handler existed)');
    }

    #[Route('/push-promo', name: 'admin_push_promo', methods: ['POST'])]
    public function pushPromo(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::(push-promo - no Node handler existed)');
    }

    #[Route('/push-new-driver', name: 'admin_push_new_driver', methods: ['POST'])]
    public function pushNewDriver(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::(push-new-driver - no Node handler existed)');
    }

    #[Route('/push-new-user', name: 'admin_push_new_user', methods: ['POST'])]
    public function pushNewUser(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::(push-new-user - no Node handler existed)');
    }

    #[Route('/push-to-admins', name: 'admin_push_to_admins', methods: ['POST'])]
    public function pushToAdmins(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::(push-to-admins - no Node handler existed)');
    }

    // ── Ride management ─────────────────────────────────────────────────

    #[Route('/rides', name: 'admin_get_all_rides', methods: ['GET'])]
    public function getAllRides(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getAllRides');
    }

    #[Route('/rides/{id}', name: 'admin_get_ride_by_id', methods: ['GET'])]
    public function getRideById(int $id): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getRideById');
    }

    #[Route('/assign-ride', name: 'admin_assign_ride', methods: ['POST'])]
    public function assignRide(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::assignRideToDriver');
    }

    #[Route('/rides/{id}/status', name: 'admin_update_ride_status', methods: ['PUT'])]
    public function updateRideStatus(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented('AdminController.js::updateRideStatus');
    }

    #[Route('/add', name: 'admin_add_ride', methods: ['POST'])]
    public function addRide(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::addRide');
    }

    #[Route('/list', name: 'admin_list_ride', methods: ['GET'])]
    public function listRide(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::listRide');
    }

    #[Route('/rides/{id}', name: 'admin_update_ride_details', methods: ['PUT'])]
    public function updateRideDetails(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented('AdminController.js::updateRideDetails');
    }

    #[Route('/search', name: 'admin_ride_search', methods: ['GET'])]
    public function rideSearch(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::rideSearch');
    }

    #[Route('/remove', name: 'admin_remove_ride', methods: ['POST'])]
    public function removeRide(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::removeRide');
    }

    // ── Driver and booking management ───────────────────────────────────

#[Route('/drivers', name: 'admin_get_all_drivers', methods: ['GET'])]
public function getAllDrivers(): JsonResponse
{
    try {
        $drivers = $this->adminService->getAllDrivers();

        $formattedDrivers = array_map(
            function (User $driver): array {
                $roles = array_map(
                    static fn ($assignment) =>
                        $assignment->getRole()->value,
                    $driver->getRoleAssignments()->toArray()
                );

                $profile = $driver->getDriverProfile();

                return [
                    'id' => $driver->getId(),
                    'name' => $driver->getName(),
                    'email' => $driver->getEmail(),
                    'avatar' => $driver->getAvatar(),
                    'roles' => $roles,

                    'profile' => $profile
                        ? [
                            'id' => $profile->getId(),
                            'phone' => $profile->getPhone(),
                            'licenseNumber' => $profile->getLicenseNumber(),
                            'vehicleType' => $profile->getVehicleType()->value,
                            'model' => $profile->getModel(),
                            'registrationNumber' => $profile->getRegistrationNumber(),
                            'capacity' => $profile->getCapacity(),
                            'rating' => $profile->getRating(),
                            'totalRides' => $profile->getTotalRides(),
                            'status' => $profile->getStatus()->value,
                            'approved' => $profile->isApproved(),
                            'isAvailable' => $profile->isAvailable(),
                        ]
                        : null,
                ];
            },
            $drivers
        );

        return $this->json([
            'success' => true,
            'drivers' => $formattedDrivers,
        ]);

    } catch (\Throwable $e) {

        return $this->json([
            'success' => false,
            'message' => 'Server error while fetching drivers.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}



#[Route('/drivers/approve/{driverId}', name: 'admin_approve_driver', methods: ['PUT'])]
public function approveDriver(
    int $driverId,
    #[CurrentUser] ?User $admin
): JsonResponse {
    try {

        $updatedProfile =
            $this->adminService->approveDriver($driverId);

        /*
         * Activity log
         */
        $this->activityLogService->log(
            user: $admin,
            action: 'APPROVE_DRIVER',
            description: $admin
                ? sprintf(
                    'Approved driver by %s.',
                    $admin->getEmail()
                )
                : 'Driver approved by administrator.'
        );

        return $this->json([
            'success' => true,
            'message' => 'Driver approved successfully.',
            'profile' => [
                'id' => $updatedProfile->getId(),
                'phone' => $updatedProfile->getPhone(),
                'licenseNumber' => $updatedProfile->getLicenseNumber(),
                'vehicleType' => $updatedProfile
                    ->getVehicleType()
                    ->value,
                'model' => $updatedProfile->getModel(),
                'registrationNumber' => $updatedProfile
                    ->getRegistrationNumber(),
                'capacity' => $updatedProfile->getCapacity(),
                'rating' => $updatedProfile->getRating(),
                'totalRides' => $updatedProfile->getTotalRides(),
                'status' => $updatedProfile
                    ->getStatus()
                    ->value,
                'approved' => $updatedProfile->isApproved(),
                'isAvailable' => $updatedProfile->isAvailable(),
            ],
        ]);

    } catch (\InvalidArgumentException $e) {

        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_BAD_REQUEST);

    } catch (\RuntimeException $e) {

        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_NOT_FOUND);

    } catch (\Throwable $e) {

        return $this->json([
            'success' => false,
            'message' => 'Server error while approving driver.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    #[Route('/add-driver', name: 'admin_add_driver', methods: ['POST'])]
    public function addDriver(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::addDriver');
    }

    #[Route('/drivers/{id}', name: 'admin_get_driver_by_id', methods: ['GET'])]
    public function getDriverById(int $id): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getDriverById');
    }

    #[Route('/drivers/{id}', name: 'admin_update_driver_details', methods: ['PUT'])]
    public function updateDriverDetails(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented('AdminController.js::updateDriverDetails');
    }



    

#[Route('/bookings', name: 'admin_get_all_bookings', methods: ['GET'])]
public function getAllBookings(
    #[CurrentUser] ?User $user
): JsonResponse {
    try {

        $bookings = $this->adminService->getAllBookings();

        $formattedBookings = array_map(
            static function (Booking $booking): array {

                $userEntity = $booking->getUser();
                $ride = $booking->getRide();
                $driver = $ride?->getDriver();

                return [
                    'id' => $booking->getId(),

                    'user' => [
                        'id' => $userEntity->getId(),
                        'name' => $userEntity->getName(),
                        'email' => $userEntity->getEmail(),
                    ],

                    'driver' => $driver
                        ? [
                            'id' => $driver->getId(),
                            'name' => $driver->getName(),
                        ]
                        : null,

                    'rides' => $booking->getRides(),

                    'ride' => $ride
                        ? [
                            'id' => $ride->getId(),
                            'pickup' => $ride->getPickup(),
                            'destination' => $ride->getDestination(),
                            'price' => $ride->getPrice(),
                            'currency' => $ride->getCurrency()->value,
                            'status' => $ride->getStatus()->value,
                        ]
                        : null,

                    'passengers' => $booking->getPassengers(),
                    'amount' => $booking->getAmount(),
                    'currency' => $booking->getCurrency()->value,
                    'address' => $booking->getAddress(),
                    'status' => $booking->getStatus()->value,
                    'payment' => $booking->isPayment(),
                    'email' => $booking->getEmail(),

                    'bookingDate' => $booking
                        ->getBookingDate()
                        ->format(\DateTimeInterface::ATOM),

                    'createdAt' => $booking
                        ->getCreatedAt()
                        ->format(\DateTimeInterface::ATOM),

                    'updatedAt' => $booking
                        ->getUpdatedAt()
                        ->format(\DateTimeInterface::ATOM),
                ];
            },
            $bookings
        );

        $this->activityLogService->log(
            $user,
            'GET_ALL_BOOKINGS',
            'Fetched all bookings'
        );

        return $this->json([
            'bookings' => $formattedBookings,
        ]);

    } catch (\Throwable $e) {

        $this->activityLogService->log(
            $user,
            'GET_ALL_BOOKINGS_FAILED',
            'Failed to fetch bookings: ' . $e->getMessage()
        );

        return $this->json([
            'message' => 'Server error.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
    // ── Logging ──────────────────────────────────────────────────────────

#[Route('/activity-logs', name: 'admin_get_activity_logs', methods: ['GET'])]
public function getActivityLogs(Request $request): JsonResponse
{
    /*
     * Match Node.js no-cache behaviour.
     */
    $response = $this->json([
        'success' => true,
    ]);

    $response->headers->set(
        'Cache-Control',
        'no-store, no-cache, must-revalidate, proxy-revalidate'
    );

    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    try {
        /*
         * Query parameters.
         */
        $page = max(
            1,
            $request->query->getInt('page', 1)
        );

        $limit = max(
            1,
            min(
                100,
                $request->query->getInt('limit', 20)
            )
        );

        $user = $request->query->get('user');
        $action = $request->query->get('action');
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $sort = strtolower(
            (string) $request->query->get('sort', 'desc')
        );

        /*
         * Parse dates.
         */
        $fromDate = null;
        $toDate = null;

        if ($from !== null && $from !== '') {
            try {
                $fromDate = new \DateTimeImmutable($from);
            } catch (\Throwable) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid "from" date.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($to !== null && $to !== '') {
            try {
                $toDate = new \DateTimeImmutable($to);

                if (
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
                ) {
                    $toDate = $toDate->setTime(23, 59, 59);
                }
            } catch (\Throwable) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid "to" date.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        /*
         * Fetch logs.
         */
        $result = $this->activityLogRepository->findPaginated(
            page: $page,
            limit: $limit,
            userKeyword: $user !== null ? trim($user) : null,
            action: $action !== null ? trim($action) : null,
            from: $fromDate,
            to: $toDate,
            sort: $sort
        );

        $logs = array_map(
            static function (ActivityLog $log): array {

                $userEntity = $log->getUser();

                return [
                    'id' => $log->getId(),

                    'user' => $userEntity
                        ? [
                            'id' => $userEntity->getId(),
                            'name' => $userEntity->getName(),
                            'email' => $userEntity->getEmail(),
                            'isOnline' => $userEntity->isOnline(),
                            'lastLoginAt' => $userEntity->getLastLoginAt()
                                ? $userEntity->getLastLoginAt()
                                    ->format(\DateTimeInterface::ATOM)
                                : null,
                            'lastActiveAt' => $userEntity->getLastActiveAt()
                                ? $userEntity->getLastActiveAt()
                                    ->format(\DateTimeInterface::ATOM)
                                : null,
                        ]
                        : null,

                    'role' => $log->getRole(),
                    'action' => $log->getAction(),
                    'description' => $log->getDescription(),
                    'ipAddress' => $log->getIpAddress(),
                    'userAgent' => $log->getUserAgent(),
                    'rawUserAgent' => $log->getRawUserAgent(),

                    'createdAt' => $log
                        ->getCreatedAt()
                        ->format(\DateTimeInterface::ATOM),
                ];
            },
            $result['logs']
        );

        $total = $result['total'];

        $response->setData([
            'success' => true,
            'page' => $page,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
            'logs' => $logs,
        ]);

        return $response;

    } catch (\Throwable $e) {

        /*
         * Log the failure.
         */
        $user = $this->getUser();

        $this->activityLogService->log(
            $user instanceof User ? $user : null,
            'GET_ACTIVITY_LOGS_FAILED',
            'Failed to fetch activity logs: ' . $e->getMessage()
        );

        $response->setStatusCode(
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        $response->setData([
            'success' => false,
            'message' => 'Server error',
        ]);

        return $response;
    }
}
}
