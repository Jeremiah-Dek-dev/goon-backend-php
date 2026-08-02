<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Replaces middlewares/AdminIPGuard.js's `adminIPGuard`.
 *
 * Node mounted this with `adminRouter.use(adminIPGuard)` immediately after
 * the `/check-ip` route, so every other /api/admin/* route (public or not)
 * is behind it. TODO: port the exact matching logic from AdminIPGuard.js -
 * this stub only does a literal comma-separated ALLOWED_IPS match, and I
 * haven't seen the original to know if it supported CIDR ranges (the
 * Node app depends on "ip-range-check", which suggests it does).
 */
class AdminIpGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.allowed_ips')]
        private readonly RateLimiterFactory $allowedIps,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/admin')) {
            return;
        }

        // Node's checkAdminIP handler (GET /check-ip) is a diagnostic
        // endpoint, not itself IP-gated - mirrored by the security.yaml
        // access_control PUBLIC_ACCESS rule for it, not here.
        if (empty(trim($this->allowedIps))) {
            return; // No allow-list configured - matches Node behavior of an empty ALLOWED_IPS
        }

        $allowed = array_map('trim', explode(',', $this->allowedIps));
        $clientIp = $request->getClientIp();

        // TODO: replace with a proper CIDR-aware check (equivalent to the
        // "ip-range-check" package) once AdminIPGuard.js is available -
        // this only does exact-match, which will reject legitimate IPs if
        // the original config relies on ranges (e.g. "10.0.0.0/24").
        if ($clientIp === null || !in_array($clientIp, $allowed, true)) {
            $event->setResponse(new JsonResponse(
                ['message' => 'Access denied: IP not allowed'],
                403
            ));
        }
    }
}
