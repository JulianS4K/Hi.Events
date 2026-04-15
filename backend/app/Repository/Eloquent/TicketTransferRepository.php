<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Models\TicketTransfer;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;

/**
 * @extends BaseRepository<TicketTransferDomainObject>
 */
class TicketTransferRepository extends BaseRepository implements TicketTransferRepositoryInterface
{
    protected function getModel(): string
    {
        return TicketTransfer::class;
    }

    public function getDomainObject(): string
    {
        return TicketTransferDomainObject::class;
    }
}
