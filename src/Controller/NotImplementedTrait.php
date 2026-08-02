<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

trait NotImplementedTrait
{
    /**
     * Placeholder response for an endpoint whose routing/security is wired
     * up but whose business logic hasn't been ported from Node yet.
     *
     * @param string $originalFunction e.g. 'AdminController.js::adminLogin'
     */
    private function notImplemented(string $originalFunction): JsonResponse
    {
        return new JsonResponse(
            ['message' => "Not yet ported: {$originalFunction}"],
            501
        );
    }
}
