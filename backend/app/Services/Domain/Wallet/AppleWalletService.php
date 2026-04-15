<?php

namespace HiEvents\Services\Domain\Wallet;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use RuntimeException;
use ZipArchive;

class AppleWalletService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.apple_wallet.enabled')
            && !empty(config('services.apple_wallet.certificate'))
            && !empty(config('services.apple_wallet.private_key'))
            && !empty(config('services.apple_wallet.pass_type_id'))
            && !empty(config('services.apple_wallet.team_id'));
    }

    /**
     * Generate a signed .pkpass and return the raw binary string.
     */
    public function generatePass(
        AttendeeDomainObject     $attendee,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject    $organizer,
    ): string {
        $passJson    = $this->buildPassJson($attendee, $event, $eventSettings, $organizer);
        $manifest    = $this->buildManifest($passJson);
        $signature   = $this->sign($manifest);

        return $this->zip([
            'pass.json'     => $passJson,
            'manifest.json' => $manifest,
            'signature'     => $signature,
        ]);
    }

    private function buildPassJson(
        AttendeeDomainObject     $attendee,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject    $organizer,
    ): string {
        $pass = [
            'formatVersion'     => 1,
            'passTypeIdentifier' => config('services.apple_wallet.pass_type_id'),
            'serialNumber'      => $attendee->getPublicId(),
            'teamIdentifier'    => config('services.apple_wallet.team_id'),
            'organizationName'  => $organizer->getName(),
            'description'       => $event->getTitle(),
            'logoText'          => $organizer->getName(),

            'backgroundColor'   => 'rgb(255, 255, 255)',
            'foregroundColor'   => 'rgb(0, 0, 0)',
            'labelColor'        => 'rgb(100, 100, 100)',

            'eventTicket' => [
                'headerFields' => [
                    [
                        'key'   => 'date',
                        'label' => 'DATE',
                        'value' => $event->getStartDate()
                            ? (new \DateTime($event->getStartDate()))->format('M d, Y')
                            : '',
                    ],
                ],
                'primaryFields' => [
                    [
                        'key'   => 'event',
                        'label' => 'EVENT',
                        'value' => $event->getTitle(),
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key'   => 'holder',
                        'label' => 'TICKET HOLDER',
                        'value' => $attendee->getFullName(),
                    ],
                ],
                'auxiliaryFields' => array_filter([
                    $eventSettings->getLocationDetails() ? [
                        'key'   => 'location',
                        'label' => 'LOCATION',
                        'value' => $eventSettings->getAddressString(),
                    ] : null,
                    [
                        'key'   => 'ticket_type',
                        'label' => 'TICKET',
                        'value' => $attendee->getPublicId(),
                    ],
                ]),
                'backFields' => [
                    [
                        'key'   => 'email',
                        'label' => 'Email',
                        'value' => $attendee->getEmail(),
                    ],
                    [
                        'key'   => 'order_ref',
                        'label' => 'Order Reference',
                        'value' => $attendee->getPublicId(),
                    ],
                ],
            ],

            'barcode' => [
                'message'         => $attendee->getPublicId(),
                'format'          => 'PKBarcodeFormatQR',
                'messageEncoding' => 'iso-8859-1',
            ],

            'barcodes' => [
                [
                    'message'         => $attendee->getPublicId(),
                    'format'          => 'PKBarcodeFormatQR',
                    'messageEncoding' => 'iso-8859-1',
                ],
            ],
        ];

        return json_encode($pass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function buildManifest(string $passJson): string
    {
        $manifest = [
            'pass.json' => sha1($passJson),
        ];

        return json_encode($manifest);
    }

    private function sign(string $manifest): string
    {
        $certPem     = base64_decode(config('services.apple_wallet.certificate'));
        $keyPem      = base64_decode(config('services.apple_wallet.private_key'));
        $keyPassword = config('services.apple_wallet.private_key_password', '');
        $wwdrPem     = base64_decode(config('services.apple_wallet.wwdr_certificate'));

        // Write to temp files (openssl_pkcs7_sign requires file paths)
        $tmpManifest = tempnam(sys_get_temp_dir(), 'pass_manifest_');
        $tmpSig      = tempnam(sys_get_temp_dir(), 'pass_sig_');
        $tmpCert     = tempnam(sys_get_temp_dir(), 'pass_cert_');
        $tmpKey      = tempnam(sys_get_temp_dir(), 'pass_key_');
        $tmpWwdr     = tempnam(sys_get_temp_dir(), 'pass_wwdr_');

        try {
            file_put_contents($tmpManifest, $manifest);
            file_put_contents($tmpCert,     $certPem);
            file_put_contents($tmpKey,      $keyPem);
            file_put_contents($tmpWwdr,     $wwdrPem);

            $cert = openssl_x509_read($certPem);
            $key  = openssl_pkey_get_private($keyPem, $keyPassword);

            if (!$cert || !$key) {
                throw new RuntimeException('Failed to load Apple Wallet certificate or private key.');
            }

            $signed = openssl_pkcs7_sign(
                $tmpManifest,
                $tmpSig,
                $cert,
                [$key, $keyPassword],
                [],
                PKCS7_BINARY | PKCS7_DETACHED,
                $tmpWwdr,
            );

            if (!$signed) {
                throw new RuntimeException('Failed to sign Apple Wallet pass manifest.');
            }

            // Strip PEM headers — extract raw DER from the PKCS#7 PEM
            $pem = file_get_contents($tmpSig);
            $pem = preg_replace('/-----BEGIN PKCS7-----/', '', $pem);
            $pem = preg_replace('/-----END PKCS7-----/', '', $pem);

            return base64_decode(preg_replace('/\s/', '', $pem));
        } finally {
            foreach ([$tmpManifest, $tmpSig, $tmpCert, $tmpKey, $tmpWwdr] as $f) {
                if (file_exists($f)) @unlink($f);
            }
        }
    }

    private function zip(array $files): string
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'pass_zip_') . '.pkpass';

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
                throw new RuntimeException('Failed to create PKPass ZIP archive.');
            }

            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }

            $zip->close();

            return file_get_contents($tmpZip);
        } finally {
            if (file_exists($tmpZip)) @unlink($tmpZip);
        }
    }
}
