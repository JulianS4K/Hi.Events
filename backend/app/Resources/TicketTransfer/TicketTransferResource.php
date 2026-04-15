<?php

namespace HiEvents\Resources\TicketTransfer;

use HiEvents\DomainObjects\TicketTransferDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketTransferDomainObject
 */
class TicketTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->getId(),
            'from_email'         => $this->getFromEmail(),
            'to_identifier'      => $this->maskedIdentifier(),
            'to_identifier_type' => $this->getToIdentifierType(),
            'transferred_at'     => $this->getTransferredAt(),
        ];
    }

    private function maskedIdentifier(): string
    {
        if ($this->getToIdentifierType() !== 'phone') {
            return $this->getToIdentifier();
        }

        $number = $this->getToIdentifier();
        $last4  = substr($number, -4);

        return '···' . $last4;
    }
}
