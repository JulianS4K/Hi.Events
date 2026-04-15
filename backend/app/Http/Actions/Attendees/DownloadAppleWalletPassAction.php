<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Wallet\AppleWalletService;
use Illuminate\Http\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class DownloadAppleWalletPassAction extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface    $eventRepository,
        private readonly AppleWalletService          $appleWalletService,
    ) {
    }

    public function __invoke(int $eventId, string $attendeeShortId): Response
    {
        if (!$this->appleWalletService->isEnabled()) {
            return response('Apple Wallet is not configured.', 503);
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

        $pass = $this->appleWalletService->generatePass(
            attendee:      $attendee,
            event:         $event,
            eventSettings: $event->getEventSettings(),
            organizer:     $event->getOrganizer(),
        );

        $filename = sprintf('ticket-%s.pkpass', $attendee->getPublicId());

        return response($pass, 200, [
            'Content-Type'        => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($pass),
        ]);
    }
}
