<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\TicketTransferDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;
use HiEvents\Resources\TicketTransfer\TicketTransferResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetAttendeeTransfersAction extends BaseAction
{
    public function __construct(
        private readonly TicketTransferRepositoryInterface $ticketTransferRepository,
    ) {
    }

    public function __invoke(int $eventId, int $attendeeId): AnonymousResourceCollection|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $transfers = $this->ticketTransferRepository->findWhere([
            TicketTransferDomainObjectAbstract::ORIGINAL_ATTENDEE_ID => $attendeeId,
            TicketTransferDomainObjectAbstract::EVENT_ID             => $eventId,
        ]);

        return TicketTransferResource::collection($transfers);
    }
}
