<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Predis\Client;
use Predis\Interface\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class CaptchaService
{
    private const REDIS_PREFIX = 'security:captcha:jti:';

    public function __construct(
        private readonly Client $redis,

        #[Autowire('%env(string:CAPTCHA_SECRET)%')]
        private readonly string $captchaSecret,

        #[Autowire('%env(int:CAPTCHA_EXPIRES)%')]
        private readonly int $captchaExpires,

        #[Autowire('%env(int:CAPTCHA_MIN_HOLD_MS)%')]
        private readonly int $minimumHold,

        #[Autowire('%env(int:CAPTCHA_MAX_HOLD_MS)%')]
        private readonly int $maximumHold,
    ) {
    }




    ## Verify captcha hold
    public function verifyHold(
        int $startedAt
    ): void {

        $now = (int) floor(
            microtime(true) * 1000
        );

        $heldTime = $now - $startedAt;

        if ($heldTime < $this->minimumHold) {
            throw new \RuntimeException(
                'CAPTCHA not held long enough.'
            );
        }

        if ($heldTime > $this->maximumHold) {
            throw new \RuntimeException(
                'Invalid hold duration.'
            );
        }
    }




    ## GENERATE CAPTCHA TOKEN
    public function generateCaptchaToken(
        Request $request
    ): string {

        $jti = Uuid::v4()->toRfc4122();

        $ip = $request->getClientIp();

        $userAgent = hash(
            'sha256',
            $request->headers->get('User-Agent', '')
        );

        $issuedAt = time();

        $payload = [

            'sub' => 'admin-captcha',

            'jti' => $jti,

            'passed' => true,

            'ip' => $ip,

            'ua' => $userAgent,

            'iat' => $issuedAt,

            'exp' => $issuedAt + $this->captchaExpires,

        ];

        $this->redis->setex(
            self::REDIS_PREFIX . $jti,
            $this->captchaExpires,
            'valid'
        );

        return JWT::encode(
            $payload,
            $this->captchaSecret,
            'HS256'
        );
    }




    ## Validate Captcha Token
    public function validateCaptcha(
        string $token,
        Request $request
    ): array {

        $payload = (array) JWT::decode(
            $token,
            new Key(
                $this->captchaSecret,
                'HS256'
            )
        );

        if (
            ($payload['passed'] ?? false) !== true
        ) {
            throw new \RuntimeException(
                'Invalid CAPTCHA.'
            );
        }

        if (
            ($payload['ip'] ?? '') !==
            $request->getClientIp()
        ) {
            throw new \RuntimeException(
                'CAPTCHA IP mismatch.'
            );
        }

        $currentUA = hash(
            'sha256',
            $request->headers->get('User-Agent', '')
        );

        if (
            ($payload['ua'] ?? '') !==
            $currentUA
        ) {
            throw new \RuntimeException(
                'CAPTCHA device mismatch.'
            );
        }

        $key =
            self::REDIS_PREFIX .
            $payload['jti'];

        if (
            !$this->redis->exists($key)
        ) {
            throw new \RuntimeException(
                'CAPTCHA has already been used.'
            );
        }

        return $payload;
    }




    
    ## Consume Captcha Token
    public function consumeCaptcha(
        array $payload
    ): void {

        $this->redis->del([
            self::REDIS_PREFIX .
            $payload['jti']
        ]);

    }




    ## REQUIRE CAPTCHA TOKEN
    public function requireValidCaptcha(
        Request $request
    ): void {

        $token =
            $request
                ->cookies
                ->get('admin_captcha');

        if (!$token) {
            throw new \RuntimeException(
                'CAPTCHA verification required.'
            );
        }

        $payload =
            $this->validateCaptcha(
                $token,
                $request
            );

        $this->consumeCaptcha(
            $payload
        );
    }





    ## INVALIDATE CAPTCHA TOKEN
    public function invalidateCaptcha(
        string $token
    ): void {

        try {

            $payload = (array) JWT::decode(
                $token,
                new Key(
                    $this->captchaSecret,
                    'HS256'
                )
            );

            $this->redis->del([
                self::REDIS_PREFIX .
                $payload['jti']
            ]);

        } catch (\Throwable) {
            // Ignore invalid or expired tokens.
        }

    }

}