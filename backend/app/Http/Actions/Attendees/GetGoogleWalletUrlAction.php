<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Wallet\GoogleWalletService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetGoogleWalletUrlAction extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface    $eventRepository,
        private readonly GoogleWalletService         $googleWalletService,
    ) {
    }

    public function __invoke(int $eventId, string $attendeeShortId): JsonResponse
    {
        if (!$this->googleWalletService->isEnabled()) {
            return $this->errorResponse('Google Wallet is not configured.', 503);
        }

        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->findById($eventId);

        if (!$event) {
            throw new ResourceNotFoundException('Event not found');
        }

        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId,
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$attendee) {
            throw new ResourceNotFoundException('Attendee not found');
        }

        $url = $this->googleWalletService->generateSaveUrl(
            attendee:      $attendee,
            event:         $event,
            eventSettings: $event->getEventSettings(),
            organizer:     $event->getOrganizer(),
        );

        return $this->jsonResponse(['url' => $url]);
    }
}
