<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Ports utils/logActivity.js (source not seen, reconstructed from its two
 * call shapes in UserController.js - one passing `req` directly, one
 * passing `ipAddress`/`userAgent` explicitly) plus the
 * `io.emit("new_activity_log", log)` broadcast that followed every call site.
 *
 * ASSUMPTION: `io.emit` (no `.to(room)`) broadcasts to ALL connected
 * clients, so this publishes to a single global Mercure topic. If activity
 * logs were meant to be admin-only, this needs a topic that only admin
 * dashboard clients are authorized to subscribe to (via the Mercure JWT's
 * subscribe claim) - not just publicly world-readable. Flagging rather
 * than deciding, since this is a real-time-topic-design question, not a
 * pure code-port question.
 */
class ActivityLogService
{
    private const ACTIVITY_LOG_TOPIC = 'https://goon.app/activity-logs';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        #private readonly HubInterface $hub,
    ) {
    }

    public function log(
        ?User $user,
        string $action,
        string $description,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ActivityLog {
        $request = $this->requestStack->getCurrentRequest();

        $entry = new ActivityLog();
        $entry->setUser($user);
        $entry->setAction($action);
        $entry->setDescription($description);
        $entry->setIpAddress($ipAddress ?? $request?->getClientIp());
        $entry->setUserAgent($userAgent ?? $request?->headers->get('User-Agent'));
        $entry->setRawUserAgent($request?->headers->get('User-Agent'));

        $this->em->persist($entry);
        $this->em->flush();

     /*   $this->hub->publish(new Update(
            self::ACTIVITY_LOG_TOPIC,
            json_encode([
                'id' => $entry->getId(),
                'action' => $entry->getAction(),
                'description' => $entry->getDescription(),
                'createdAt' => $entry->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR)
        ));
        */
        return $entry;
    }
}
