<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Attendee\QrTokenService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetQrTokenPublicAction extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface    $eventRepository,
        private readonly QrTokenService              $qrTokenService,
    ) {
    }

    public function __invoke(int $eventId, string $attendeeShortId): JsonResponse
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($eventId);

        if (!$event) {
            return $this->notFoundResponse();
        }

        if (!$event->getEventSettings()?->getQrRotationEnabled()) {
            return $this->errorResponse(__('QR rotation is not enabled for this event.'), 403);
        }

        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId,
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$attendee) {
            return $this->notFoundResponse();
        }

        return $this->jsonResponse($this->qrTokenService->generateToken($attendee));
    }
}
