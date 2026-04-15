<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TransferTicketDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $orderShortId,
        public readonly string $attendeeShortId,
        public readonly string $toIdentifier,       // email address or phone number
        public readonly string $toIdentifierType,   // 'email' or 'phone'
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
    ) {
    }
}
