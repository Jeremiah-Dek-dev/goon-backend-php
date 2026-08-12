<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleMapsService
{
    private const GEOCODING_URL =
        'https://geocode.googleapis.com/v4/geocode/address';

    private const ROUTES_URL =
        'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $googleMapsApiKey,
    ) {
    }

    /**
     * Convert an address into coordinates.
     *
     * Google Geocoding API v4.
     *
     * @return array{
     *     address: string,
     *     latitude: float,
     *     longitude: float,
     *     placeId: ?string
     * }
     */
    public function geocode(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            throw new \InvalidArgumentException(
                'Address cannot be empty.'
            );
        }

        /*
         * Google Geocoding v4 supports:
         *
         * GET /v4/geocode/address/{addressQuery}
         *
         * The unstructured address belongs in the PATH,
         * not ?address=...
         */
        $url = sprintf(
            '%s/%s',
            self::GEOCODING_URL,
            rawurlencode($address)
        );

        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'headers' => [
                        'X-Goog-Api-Key' =>
                            $this->googleMapsApiKey,

                        'Accept' =>
                            'application/json',
                    ],

                    'timeout' => 10,
                ]
            );

            $statusCode = $response->getStatusCode();

            $body = $response->getContent(false);

            if ($statusCode !== 200) {
                throw new \RuntimeException(
                    sprintf(
                        'Google Geocoding API returned HTTP %d: %s',
                        $statusCode,
                        $body
                    )
                );
            }

            $data = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        } catch (\Throwable $e) {

            if (
                $e instanceof \RuntimeException
                && str_contains(
                    $e->getMessage(),
                    'Google Geocoding API returned'
                )
            ) {
                throw $e;
            }

            throw new \RuntimeException(
                'Unable to contact the Google Geocoding service.',
                0,
                $e
            );
        }

        $results = $data['results'] ?? [];

        if (
            !is_array($results)
            || empty($results)
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unable to find location: %s',
                    $address
                )
            );
        }

        $result = $results[0];

        $location = $result['location'] ?? null;

        if (
            !is_array($location)
            || !isset(
                $location['latitude'],
                $location['longitude']
            )
        ) {
            throw new \RuntimeException(
                'Geocoding response did not contain valid coordinates.'
            );
        }

        return [
            'address' =>
                (string) (
                    $result['formattedAddress']
                    ?? $address
                ),

            'latitude' =>
                (float) $location['latitude'],

            'longitude' =>
                (float) $location['longitude'],

            'placeId' =>
                isset($result['placeId'])
                    ? (string) $result['placeId']
                    : null,
        ];
    }

    /**
     * Calculate a driving route between two coordinates.
     *
     * @return array{
     *     distanceMeters: float,
     *     distanceKm: float,
     *     durationSeconds: int,
     *     duration: string
     * }
     */
    public function calculateRoute(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude
    ): array {
        $payload = [
            'origin' => [
                'location' => [
                    'latLng' => [
                        'latitude' =>
                            $originLatitude,

                        'longitude' =>
                            $originLongitude,
                    ],
                ],
            ],

            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude' =>
                            $destinationLatitude,

                        'longitude' =>
                            $destinationLongitude,
                    ],
                ],
            ],

            'travelMode' =>
                'DRIVE',

            'routingPreference' =>
                'TRAFFIC_AWARE',

            'computeAlternativeRoutes' =>
                false,

            'units' =>
                'METRIC',
        ];

        try {
            $response = $this->httpClient->request(
                'POST',
                self::ROUTES_URL,
                [
                    'headers' => [
                        'X-Goog-Api-Key' =>
                            $this->googleMapsApiKey,

                        'X-Goog-FieldMask' =>
                            'routes.distanceMeters,routes.duration',

                        'Content-Type' =>
                            'application/json',

                        'Accept' =>
                            'application/json',
                    ],

                    'json' =>
                        $payload,

                    'timeout' =>
                        10,
                ]
            );

            $statusCode =
                $response->getStatusCode();

            $body =
                $response->getContent(false);

            if ($statusCode !== 200) {
                throw new \RuntimeException(
                    sprintf(
                        'Google Routes API returned HTTP %d: %s',
                        $statusCode,
                        $body
                    )
                );
            }

            $data = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        } catch (\Throwable $e) {

            if (
                $e instanceof \RuntimeException
                && str_contains(
                    $e->getMessage(),
                    'Google Routes API returned'
                )
            ) {
                throw $e;
            }

            throw new \RuntimeException(
                'Unable to calculate route.',
                0,
                $e
            );
        }

        $route =
            $data['routes'][0] ?? null;

        if (!is_array($route)) {
            throw new \RuntimeException(
                'No route could be calculated between the selected locations.'
            );
        }

        $distanceMeters =
            (float) (
                $route['distanceMeters']
                ?? 0
            );

        $durationRaw =
            $route['duration']
            ?? null;

        if (
            $distanceMeters <= 0
            || !is_string($durationRaw)
            || $durationRaw === ''
        ) {
            throw new \RuntimeException(
                'Invalid route information returned by Google.'
            );
        }

        $durationSeconds =
            $this->parseDuration(
                $durationRaw
            );

        return [
            'distanceMeters' =>
                $distanceMeters,

            'distanceKm' =>
                round(
                    $distanceMeters / 1000,
                    2
                ),

            'durationSeconds' =>
                $durationSeconds,

            'duration' =>
                $this->formatDuration(
                    $durationSeconds
                ),
        ];
    }

    private function parseDuration(
        string $duration
    ): int {
        if (
            !preg_match(
                '/^(\d+)s$/',
                trim($duration),
                $matches
            )
        ) {
            throw new \RuntimeException(
                'Invalid duration returned by Google.'
            );
        }

        return (int) $matches[1];
    }

    private function formatDuration(
        int $seconds
    ): string {
        $minutes =
            (int) ceil($seconds / 60);

        if ($minutes < 60) {
            return sprintf(
                '%d mins',
                $minutes
            );
        }

        $hours =
            intdiv($minutes, 60);

        $remainingMinutes =
            $minutes % 60;

        if ($remainingMinutes === 0) {
            return sprintf(
                '%d hr%s',
                $hours,
                $hours === 1 ? '' : 's'
            );
        }

        return sprintf(
            '%d hr%s %d mins',
            $hours,
            $hours === 1 ? '' : 's',
            $remainingMinutes
        );
    }
}
