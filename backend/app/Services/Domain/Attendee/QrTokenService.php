<?php

namespace HiEvents\Services\Domain\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;

class QrTokenService
{
    private const WINDOW_SECONDS = 30;
    private const DRIFT_WINDOWS  = 1; // accept ±1 window for clock skew

    public function generateToken(AttendeeDomainObject $attendee): array
    {
        $window = $this->currentWindow();
        $token  = $this->buildToken($attendee->getPublicId(), $window);

        return [
            'token'       => $token,
            'window'      => $window,
            'valid_until' => ($window + 1) * self::WINDOW_SECONDS,
        ];
    }

    /**
     * Validate a token and return the public_id if valid, null otherwise.
     */
    public function validateToken(string $token): ?string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$publicId, $claimedWindow, $claimedHmac] = $parts;
        $claimedWindow  = (int) $claimedWindow;
        $currentWindow  = $this->currentWindow();

        // Fast-fail: claimed window must be within drift range
        if (abs($currentWindow - $claimedWindow) > self::DRIFT_WINDOWS) {
            return null;
        }

        $expectedHmac = $this->hmac($publicId, $claimedWindow);

        if (!hash_equals($expectedHmac, $claimedHmac)) {
            return null;
        }

        return $publicId;
    }

    public function isRotatingToken(string $value): bool
    {
        return substr_count($value, '.') === 2;
    }

    private function buildToken(string $publicId, int $window): string
    {
        $hmac = $this->hmac($publicId, $window);
        return sprintf('%s.%d.%s', $publicId, $window, $hmac);
    }

    private function hmac(string $publicId, int $window): string
    {
        $secret = config('app.key');
        return hash_hmac('sha256', $publicId . '.' . $window, $secret);
    }

    private function currentWindow(): int
    {
        return (int) floor(time() / self::WINDOW_SECONDS);
    }
}
