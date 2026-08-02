<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ported from routes/DriverRoute.js. Auth boundary (driverAuth middleware
 * -> ROLE_DRIVER) is enforced by security.yaml's access_control, not here.
 * Logic needs porting from controllers/DriverController.js and, for the
 * one cross-controller route, controllers/RideController.js::rateRide.
 */
#[Route('/api/driver')]
class DriverController extends AbstractController
{
    use NotImplementedTrait;

    // ── Public routes ────────────────────────────────────────────────────

    #[Route('/register', name: 'driver_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Was: upload.single("avatar") - Multer -> $request->files->get('avatar')
        return $this->notImplemented('DriverController.js::registerDriver');
    }

    #[Route('/form-submitted', name: 'driver_form_submitted', methods: ['GET'])]
    public function formSubmitted(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::formSubmitted');
    }

    #[Route('/login', name: 'driver_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::loginDriver');
    }

    #[Route('/forgot-password', name: 'driver_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::forgotPassword');
    }

    #[Route('/reset-password/{token}', name: 'driver_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, string $token): JsonResponse
    {
        return $this->notImplemented('DriverController.js::resetPassword');
    }

    #[Route('/refresh-token', name: 'driver_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::driverTokenRefresh');
    }

    #[Route('/commission-rate', name: 'driver_commission_rate', methods: ['GET'])]
    public function commissionRate(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getCommissionRate');
    }

    // ── Protected (driverAuth equivalent) ───────────────────────────────

    #[Route('/me', name: 'driver_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getDriverProfile');
    }

    #[Route('/profile', name: 'driver_update_profile', methods: ['POST'])]
    public function updateProfile(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::updateDriverProfile');
    }

    #[Route('/logout', name: 'driver_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::driverLogout');
    }

    #[Route('/add', name: 'driver_add_ride', methods: ['POST'])]
    public function addRide(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::addRide');
    }

    #[Route('/{driverId}/rides', name: 'driver_get_rides', methods: ['GET'])]
    public function getDriverRides(int $driverId): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getDriverRides');
    }

    #[Route('/rides/{id}', name: 'driver_get_ride_by_id', methods: ['GET'])]
    public function getRideById(int $id): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getRideById');
    }

    #[Route('/rides/{id}', name: 'driver_update_ride', methods: ['PUT'])]
    public function updateRide(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented('DriverController.js::updateRide');
    }

    #[Route('/rides/{id}', name: 'driver_delete_ride', methods: ['DELETE'])]
    public function deleteRide(int $id): JsonResponse
    {
        return $this->notImplemented('DriverController.js::deleteRide');
    }

    #[Route('/ride/status', name: 'driver_update_ride_status', methods: ['POST'])]
    public function updateRideStatus(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::updateRideStatusDriver');
    }

    #[Route('/current-ride', name: 'driver_current_ride', methods: ['GET'])]
    public function currentRide(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getCurrentRide');
    }

    #[Route('/current-rides', name: 'driver_current_rides', methods: ['GET'])]
    public function currentRides(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getCurrentRides');
    }

    #[Route('/upcoming-rides', name: 'driver_upcoming_rides', methods: ['GET'])]
    public function upcomingRides(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getUpcomingRides');
    }

    #[Route('/arrive', name: 'driver_arrive_at_pickup', methods: ['POST'])]
    public function arriveAtPickup(Request $request): JsonResponse
    {
        // Likely a Mercure publish point once the real-time phase lands
        // (was presumably a socket emit in the Node version).
        return $this->notImplemented('DriverController.js::arriveAtPickup');
    }

    #[Route('/driver-bookings', name: 'driver_bookings', methods: ['GET'])]
    public function driverBookings(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getDriverBookings');
    }

    #[Route('/startRide/{rideId}', name: 'driver_start_ride', methods: ['POST'])]
    public function startRide(int $rideId): JsonResponse
    {
        return $this->notImplemented('DriverController.js::startRide');
    }

    #[Route('/{driverId}/status', name: 'driver_toggle_status', methods: ['PATCH'])]
    public function toggleStatus(int $driverId): JsonResponse
    {
        return $this->notImplemented('DriverController.js::toggleDriverStatus');
    }

    #[Route('/completeRide/{bookingId}', name: 'driver_complete_ride', methods: ['POST'])]
    public function completeRide(int $bookingId): JsonResponse
    {
        return $this->notImplemented('DriverController.js::completeRide');
    }

    #[Route('/{driverId}/stats', name: 'driver_stats', methods: ['GET'])]
    public function driverStats(int $driverId): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getDriverStats');
    }

    #[Route('/ride/cancel', name: 'driver_cancel_ride', methods: ['POST'])]
    public function cancelRide(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::cancelRide');
    }

    #[Route('/earnings', name: 'driver_earnings', methods: ['GET'])]
    public function earnings(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getDriverEarnings');
    }

    #[Route('/earnings-report', name: 'driver_earnings_report', methods: ['GET'])]
    public function earningsReport(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getDriverEarningsReport');
    }

    #[Route('/history', name: 'driver_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getDriverHistory');
    }

    #[Route('/performance-metrics', name: 'driver_performance_metrics', methods: ['GET'])]
    public function performanceMetrics(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getPerformanceMetrics');
    }

    #[Route('/support', name: 'driver_support', methods: ['POST'])]
    public function support(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::submitSupportRequest');
    }

    #[Route('/rate', name: 'driver_rate_ride', methods: ['POST'])]
    public function rateRide(Request $request): JsonResponse
    {
        return $this->notImplemented('RideController.js::rateRide');
    }

    #[Route('/ride/fare', name: 'driver_update_ride_fare', methods: ['PUT'])]
    public function updateRideFare(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::updateRideFare');
    }

    #[Route('/pending-ride-requests', name: 'driver_pending_ride_requests', methods: ['GET'])]
    public function pendingRideRequests(): JsonResponse
    {
        return $this->notImplemented('DriverController.js::getPendingRideRequests');
    }

    #[Route('/ride/respond', name: 'driver_respond_to_ride', methods: ['POST'])]
    public function respondToRide(Request $request): JsonResponse
    {
        return $this->notImplemented('DriverController.js::driverRespondToRide');
    }
}
