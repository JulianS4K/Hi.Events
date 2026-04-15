<?php

namespace HiEvents\Services\Domain\Wallet;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use RuntimeException;

class GoogleWalletService
{
    private const SAVE_URL = 'https://pay.google.com/gp/v/save/';

    public function isEnabled(): bool
    {
        return (bool) config('services.google_wallet.enabled')
            && !empty(config('services.google_wallet.issuer_id'))
            && !empty(config('services.google_wallet.service_account_json'));
    }

    /**
     * Returns a Google Wallet save URL containing a signed JWT.
     */
    public function generateSaveUrl(
        AttendeeDomainObject     $attendee,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject    $organizer,
    ): string {
        $serviceAccount = json_decode(config('services.google_wallet.service_account_json'), true);

        if (!$serviceAccount || empty($serviceAccount['private_key'])) {
            throw new RuntimeException('Google Wallet service account JSON is invalid or missing private_key.');
        }

        $issuerId  = config('services.google_wallet.issuer_id');
        $classId   = $issuerId . '.' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $event->getSlug() ?: $event->getId());
        $objectId  = $issuerId . '.' . $attendee->getPublicId();

        $payload = [
            'iss' => $serviceAccount['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => [
                'eventTicketClasses' => [
                    $this->buildEventClass($classId, $event, $eventSettings, $organizer),
                ],
                'eventTicketObjects' => [
                    $this->buildEventObject($objectId, $classId, $attendee, $event),
                ],
            ],
        ];

        $jwt = $this->signJwt($payload, $serviceAccount['private_key']);

        return self::SAVE_URL . $jwt;
    }

    private function buildEventClass(
        string                   $classId,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject    $organizer,
    ): array {
        $class = [
            'id'           => $classId,
            'issuerName'   => $organizer->getName(),
            'eventName'    => ['defaultValue' => ['language' => 'en', 'value' => $event->getTitle()]],
            'reviewStatus' => 'UNDER_REVIEW',
        ];

        if ($event->getStartDate()) {
            $class['dateTime']['start'] = (new \DateTime($event->getStartDate()))->format(\DateTime::RFC3339);
        }

        if ($event->getEndDate()) {
            $class['dateTime']['end'] = (new \DateTime($event->getEndDate()))->format(\DateTime::RFC3339);
        }

        if ($eventSettings->getLocationDetails()) {
            $class['venue'] = [
                'name'    => ['defaultValue' => ['language' => 'en', 'value' => $eventSettings->getLocationDetails()]],
                'address' => ['defaultValue' => ['language' => 'en', 'value' => $eventSettings->getAddressString()]],
            ];
        }

        return $class;
    }

    private function buildEventObject(
        string               $objectId,
        string               $classId,
        AttendeeDomainObject $attendee,
        EventDomainObject    $event,
    ): array {
        return [
            'id'      => $objectId,
            'classId' => $classId,
            'state'   => 'ACTIVE',
            'ticketHolderName' => $attendee->getFullName(),
            'ticketNumber'     => $attendee->getPublicId(),
            'barcode' => [
                'type'  => 'QR_CODE',
                'value' => $attendee->getPublicId(),
            ],
            'textModulesData' => [
                [
                    'header' => 'Ticket ID',
                    'body'   => $attendee->getPublicId(),
                    'id'     => 'ticket_id',
                ],
                [
                    'header' => 'Email',
                    'body'   => $attendee->getEmail(),
                    'id'     => 'email',
                ],
            ],
        ];
    }

    /**
     * Sign a payload as a JWT using RS256 with the service account private key.
     */
    private function signJwt(array $payload, string $privateKey): string
    {
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $body   = $this->base64url(json_encode($payload));

        $data = $header . '.' . $body;

        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new RuntimeException('Failed to load Google Wallet service account private key.');
        }

        openssl_sign($data, $signature, $key, 'SHA256');

        return $data . '.' . $this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
