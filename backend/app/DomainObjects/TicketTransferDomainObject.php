<?php

namespace HiEvents\DomainObjects;

class TicketTransferDomainObject extends Generated\TicketTransferDomainObjectAbstract
{
    public function isEmailTransfer(): bool
    {
        return $this->to_identifier_type === 'email';
    }

    public function isPhoneTransfer(): bool
    {
        return $this->to_identifier_type === 'phone';
    }
}
