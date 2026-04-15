<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\CheckInAction;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CheckInAttendeeDTO;
use HiEvents\Services\Domain\Attendee\QrTokenService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class CheckInAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly UserRepositoryInterface     $userRepository,
        private readonly LoggerInterface             $logger,
        private readonly QrTokenService              $qrTokenService,
    ) {
    }

    /**
     * @throws CannotCheckInException
     * @throws ResourceNotFoundException
     */
    public function handle(CheckInAttendeeDTO $checkInAttendeeDTO): AttendeeDomainObject
    {
        $publicId = $this->resolvePublicId(
            $checkInAttendeeDTO->attendee_public_id,
            $checkInAttendeeDTO->event_id,
        );

        if ($publicId !== $checkInAttendeeDTO->attendee_public_id) {
            $checkInAttendeeDTO = new CheckInAttendeeDTO(
                attendee_public_id:     $publicId,
                event_id:               $checkInAttendeeDTO->event_id,
                action:                 $checkInAttendeeDTO->action,
                checked_in_by_user_id:  $checkInAttendeeDTO->checked_in_by_user_id,
            );
        }

        $attendee = $this->fetchAttendee($checkInAttendeeDTO);

        $this->validateAttendeeStatus($attendee);
        $this->validateAction($attendee, $checkInAttendeeDTO);

        $this->updateCheckInStatus($checkInAttendeeDTO);

        return $this->fetchAttendee($checkInAttendeeDTO);
    }

    /**
     * Accepts either a raw public_id or a rotating HMAC token.
     * Returns the resolved public_id in both cases.
     *
     * @throws CannotCheckInException
     */
    private function resolvePublicId(string $value, int $eventId): string
    {
        if (!$this->qrTokenService->isRotatingToken($value)) {
            return $value;
        }

        $publicId = $this->qrTokenService->validateToken($value);

        if ($publicId === null) {
            throw new CannotCheckInException(__('QR code has expired. Ask the attendee to refresh their ticket.'));
        }

        return $publicId;
    }

    private function fetchAttendee(CheckInAttendeeDTO $checkInAttendeeDTO): AttendeeDomainObject
    {
        $criteria = [
            AttendeeDomainObjectAbstract::PUBLIC_ID => $checkInAttendeeDTO->attendee_public_id,
            AttendeeDomainObjectAbstract::EVENT_ID => $checkInAttendeeDTO->event_id,
        ];

        $attendee = $this->attendeeRepository->findFirstWhere($criteria);

        if (!$attendee) {
            throw new ResourceNotFoundException();
        }

        return $attendee;
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateAttendeeStatus(AttendeeDomainObject $attendee): void
    {
        if ($attendee->getStatus() !== AttendeeStatus::ACTIVE->name) {
            $this->logger->info(
                'Attempted to check in attendee that is not active',
                [
                    'attendee_public_id' => $attendee->getPublicId(),
                    'event_id' => $attendee->getEventId(),
                ]
            );

            throw new CannotCheckInException(__('Cannot check in attendee as they are not active.'));
        }
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateAction(AttendeeDomainObject $attendee, CheckInAttendeeDTO $checkInAttendeeDTO): void
    {
        $actionName = $checkInAttendeeDTO->action === CheckInAction::CHECK_IN ? __('in') : __('out');
        $isInvalidCheckIn = $attendee->getCheckedInAt() !== null && $checkInAttendeeDTO->action === CheckInAction::CHECK_IN;
        $isInvalidCheckOut = $attendee->getCheckedInAt() === null && $checkInAttendeeDTO->action === CheckInAction::CHECK_OUT;

        if ($isInvalidCheckIn || $isInvalidCheckOut) {
            $user = $this->userRepository->findById(
                $checkInAttendeeDTO->action === CheckInAction::CHECK_IN ? $attendee->getCheckedInBy() : $attendee->getCheckedOutBy()
            );

            throw new CannotCheckInException(
                __(
                    "Cannot check :actionName attendee as they were already checked :actionName by :fullName :time.",
                    [
                        'actionName' => $actionName,
                        'fullName' => $user->getFullName(),
                        'time' => $checkInAttendeeDTO->action === CheckInAction::CHECK_IN
                            ? Carbon::createFromTimeString($attendee->getCheckedInAt())->ago()
                            : '',
                    ]
                )
            );
        }
    }

    private function updateCheckInStatus(CheckInAttendeeDTO $checkInAttendeeDTO): void
    {
        $updateData = [
            AttendeeDomainObjectAbstract::CHECKED_IN_AT => $checkInAttendeeDTO->action === CheckInAction::CHECK_IN
                ? now()
                : null,
            AttendeeDomainObjectAbstract::CHECKED_IN_BY => $checkInAttendeeDTO->action === CheckInAction::CHECK_IN
                ? $checkInAttendeeDTO->checked_in_by_user_id
                : null,
            AttendeeDomainObjectAbstract::CHECKED_OUT_BY => $checkInAttendeeDTO->action === CheckInAction::CHECK_OUT
                ? $checkInAttendeeDTO->checked_in_by_user_id
                : null,
        ];

        $criteria = [
            AttendeeDomainObjectAbstract::PUBLIC_ID => $checkInAttendeeDTO->attendee_public_id,
            AttendeeDomainObjectAbstract::EVENT_ID => $checkInAttendeeDTO->event_id,
        ];

        $this->attendeeRepository->updateWhere($updateData, $criteria);

        $this->logger->info(
            'Attendee checked ' . $checkInAttendeeDTO->action . ' by user ' . $checkInAttendeeDTO->checked_in_by_user_id,
            [
                'attendee_public_id' => $checkInAttendeeDTO->attendee_public_id,
                'event_id' => $checkInAttendeeDTO->event_id,
            ]
        );
    }
}
