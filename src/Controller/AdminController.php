<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
/**
 * Ported from routes/AdminRoute.js. Auth boundaries are enforced by
 * config/packages/security.yaml's access_control (AdminIpGuardSubscriber
 * covers all of /api/admin; role checks split pre-/post-verifyAdmin exactly
 * as the Node router did) - NOT re-checked here. Method bodies are stubs;
 * logic needs porting from controllers/AdminController.js,
 * controllers/verifyBackupCodes.js, controllers/generateBackupCodes.js,
 * controllers/AdminInvite.js, controllers/AcceptInvite.js, and
 * controllers/GetActivityLog.js.
 */
#[Route('/api/admin')]
class AdminController extends AbstractController
{
    use NotImplementedTrait;

    public function __construct(
        
    #[Autowire(service: 'limiter.admin_login')]
    private readonly RateLimiterFactory $adminLoginLimiter,

    #[Autowire(service: 'limiter.captcha')]
    private readonly RateLimiterFactory $captchaLimiter,
    ) {
    }

    // ── Pre-auth (IP-guarded only) ──────────────────────────────────────

    #[Route('/check-ip', name: 'admin_check_ip', methods: ['GET'])]
    public function checkIp(Request $request): JsonResponse
    {
        // Was: checkAdminIP in middlewares/AdminIPGuard.js
        return $this->notImplemented('AdminIPGuard.js::checkAdminIP');
    }

    #[Route('/login', name: 'admin_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $limit = $this->adminLoginLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(['message' => 'Too many login attempts.'], 429);
        }

        return $this->notImplemented('AdminController.js::adminLogin');
    }

    #[Route('/verify-captcha-hold', name: 'admin_verify_captcha_hold', methods: ['POST'])]
    public function verifyCaptchaHold(Request $request): JsonResponse
    {
        $limit = $this->captchaLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(['message' => 'Too many captcha attempts.'], 429);
        }

        return $this->notImplemented('AdminController.js::verifyRecaptchaHold');
    }

    #[Route('/forgot-password', name: 'admin_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::forgotPassword');
    }

    #[Route('/reset-password/{token}', name: 'admin_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, string $token): JsonResponse
    {
        return $this->notImplemented('AdminController.js::resetPassword');
    }

    #[Route('/accept-invite', name: 'admin_accept_invite', methods: ['POST'])]
    public function acceptInvite(Request $request): JsonResponse
    {
        return $this->notImplemented('AcceptInvite.js::acceptInvite');
    }

    #[Route('/verify-2fa', name: 'admin_verify_2fa', methods: ['POST'])]
    public function verify2FA(Request $request): JsonResponse
    {
        // Was: gated by middlewares/verifyPre2FA.js in Node. In Symfony this
        // becomes scheb/2fa-bundle's own pre-auth-token flow - see the
        // `two_factor:` block on the `admin` firewall in security.yaml.
        return $this->notImplemented('AdminController.js::verify2FA');
    }

    #[Route('/setup-2fa', name: 'admin_setup_2fa', methods: ['GET'])]
    public function setup2FA(Request $request): JsonResponse
    {
        // Needs endroid/qr-code (not yet in composer.json) to replace the
        // Node `qrcode` package for rendering the TOTP setup QR.
        return $this->notImplemented('AdminController.js::setup2FA');
    }

    #[Route('/verify-backup-code', name: 'admin_verify_backup_code', methods: ['POST'])]
    public function verifyBackupCode(Request $request): JsonResponse
    {
        return $this->notImplemented('verifyBackupCodes.js::verifyBackupCode');
    }

    #[Route('/regenerate-backup-codes', name: 'admin_regenerate_backup_codes', methods: ['POST'])]
    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        return $this->notImplemented('generateBackupCodes.js::regenerateBackupCodes');
    }

    #[Route('/refresh-token', name: 'admin_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::adminTokenRefresh');
    }

    #[Route('/csrf-token', name: 'admin_csrf_token', methods: ['GET'])]
    public function csrfToken(Request $request): JsonResponse
    {
        // Symfony's CSRF is generated via the injected CsrfTokenManagerInterface,
        // not a custom middleware - straightforward once wired up.
        return $this->notImplemented('csrf.js::generateCsrfToken');
    }

    #[Route('/commission', name: 'admin_get_commission', methods: ['GET'])]
    public function getCommission(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getCommissionRate');
    }

    // ── Post-auth (verifyAdmin equivalent - see security.yaml access_control) ──

    #[Route('/me', name: 'admin_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getAdminProfile');
    }

    #[Route('/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::adminLogout');
    }

    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getDashboardStats');
    }

    #[Route('/invite', name: 'admin_invite', methods: ['POST'])]
    public function invite(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminInvite.js::inviteAdmin');
    }

    #[Route('/pending-invites', name: 'admin_pending_invites', methods: ['GET'])]
    public function pendingInvites(): JsonResponse
    {
        return $this->notImplemented('AdminInvite.js::listPendingInvites');
    }

    #[Route('/invite/{id}', name: 'admin_cancel_invite', methods: ['DELETE'])]
    public function cancelInvite(int $id): JsonResponse
    {
        return $this->notImplemented('AdminInvite.js::cancelInvite');
    }

    #[Route('/profile/avatar', name: 'admin_profile_avatar', methods: ['POST'])]
    public function updateAvatar(Request $request): JsonResponse
    {
        // Was: upload.single('avatar') (Multer -> Cloudinary storage).
        // Symfony equivalent: $request->files->get('avatar') + cloudinary/cloudinary_php.
        return $this->notImplemented('AdminController.js::updateAdminAvatar');
    }

    #[Route('/profile/password', name: 'admin_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::changePassword');
    }

    #[Route('/monthly-revenue', name: 'admin_monthly_revenue', methods: ['GET'])]
    public function monthlyRevenue(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getMonthlyRevenue');
    }

    #[Route('/booking-status', name: 'admin_booking_status', methods: ['GET'])]
    public function bookingStatus(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getBookingStatusDistribution');
    }

    #[Route('/monthly-bookings', name: 'admin_monthly_bookings', methods: ['GET'])]
    public function monthlyBookings(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getMonthlyBookings');
    }

    #[Route('/commission', name: 'admin_set_commission', methods: ['POST'])]
    public function setCommission(Request $request): JsonResponse
    {
        return $this->notImplemented('AdminController.js::setCommissionRate');
    }

    // ── Push notifications ──────────────────────────────────────────────
    // Node left several of these routes registered with NO handler
    // (`adminRouter.post("/push-to-drivers", );` etc. - second arg missing).
    // That's very likely a bug in the original app (Express would 500 on
    // an actual request), not intentional. Flagging rather than "fixing"
    // silently - confirm with whoever owns AdminController.js whether these
    // were ever supposed to be wired up, and to what.

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
        return $this->notImplemented('AdminController.js::getAllDrivers');
    }

    #[Route('/drivers/approve/{driverId}', name: 'admin_approve_driver', methods: ['PUT'])]
    public function approveDriver(int $driverId): JsonResponse
    {
        return $this->notImplemented('AdminController.js::approveDriver');
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
    public function getAllBookings(): JsonResponse
    {
        return $this->notImplemented('AdminController.js::getAllBookings');
    }

    // ── Logging ──────────────────────────────────────────────────────────

    #[Route('/activity-logs', name: 'admin_activity_logs', methods: ['GET'])]
    public function activityLogs(): JsonResponse
    {
        return $this->notImplemented('GetActivityLog.js::getActivityLogs');
    }
}
