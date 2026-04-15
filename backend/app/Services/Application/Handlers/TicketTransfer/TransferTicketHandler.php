<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketTransferDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Helper\IdHelper;
use HiEvents\Mail\TicketTransfer\TicketTransferMail;
use HiEvents\Mail\TicketTransfer\TicketTransferredSenderMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\TransferTicketDTO;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class TransferTicketHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface       $attendeeRepository,
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly EventRepositoryInterface          $eventRepository,
        private readonly ProductRepositoryInterface        $productRepository,
        private readonly TicketTransferRepositoryInterface $ticketTransferRepository,
        private readonly OrderAuditLogService              $orderAuditLogService,
        private readonly ConnectionInterface               $db,
        private readonly Mailer                            $mailer,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(TransferTicketDTO $dto): AttendeeDomainObject
    {
        $event = $this->loadEvent($dto->eventId);
        $order = $this->loadOrder($dto->orderShortId, $dto->eventId);

        $attendee = $this->loadAttendee($dto->attendeeShortId, $order->getId(), $dto->eventId);
        $product  = $this->loadProduct($attendee->getProductId(), $dto->eventId);

        $this->validateTransferEligibility($attendee, $product, $order, $dto);

        return $this->db->transaction(function () use ($dto, $attendee, $event, $product) {
            return $this->executeTransfer($dto, $attendee, $event, $product);
        });
    }

    private function loadEvent(int $eventId): EventDomainObject
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($eventId);

        if (!$event) {
            throw new ResourceNotFoundException(__('Event not found'));
        }

        return $event;
    }

    private function loadOrder(string $orderShortId, int $eventId): OrderDomainObject
    {
        $order = $this->orderRepository->findByShortId($orderShortId);

        if (!$order || $order->getEventId() !== $eventId) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $order;
    }

    /**
     * @throws ValidationException
     */
    private function loadAttendee(string $shortId, int $orderId, int $eventId): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $shortId,
            AttendeeDomainObjectAbstract::ORDER_ID => $orderId,
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$attendee) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        return $attendee;
    }

    private function loadProduct(int $productId, int $eventId): ProductDomainObject
    {
        $product = $this->productRepository->findFirstWhere([
            ProductDomainObjectAbstract::ID       => $productId,
            ProductDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$product) {
            throw new ResourceNotFoundException(__('Ticket type not found'));
        }

        return $product;
    }

    /**
     * @throws ValidationException
     */
    private function validateTransferEligibility(
        AttendeeDomainObject $attendee,
        ProductDomainObject  $product,
        OrderDomainObject    $order,
        TransferTicketDTO    $dto,
    ): void {
        if ($order->isCancelled()) {
            throw ValidationException::withMessages([
                'attendee' => __('Tickets on cancelled orders cannot be transferred.'),
            ]);
        }

        if (!$order->isCompleted()) {
            throw ValidationException::withMessages([
                'attendee' => __('Tickets can only be transferred once the order is complete.'),
            ]);
        }

        if ($attendee->getStatus() !== AttendeeStatus::ACTIVE->name) {
            throw ValidationException::withMessages([
                'attendee' => __('Only active tickets can be transferred.'),
            ]);
        }

        if ($product->getIsTransferable() === false) {
            throw ValidationException::withMessages([
                'attendee' => __('This ticket type cannot be transferred.'),
            ]);
        }

        if ($dto->toIdentifierType === 'email' && strtolower($dto->toIdentifier) === strtolower($attendee->getEmail())) {
            throw ValidationException::withMessages([
                'to_identifier' => __('You cannot transfer a ticket to yourself.'),
            ]);
        }

        if ($dto->toIdentifierType === 'phone' && $dto->toIdentifier === $attendee->getPhone()) {
            throw ValidationException::withMessages([
                'to_identifier' => __('You cannot transfer a ticket to yourself.'),
            ]);
        }
    }

    private function executeTransfer(
        TransferTicketDTO    $dto,
        AttendeeDomainObject $attendee,
        EventDomainObject    $event,
        ProductDomainObject  $product,
    ): AttendeeDomainObject {
        $fromEmail    = $attendee->getEmail();
        $newShortId   = IdHelper::shortId(IdHelper::ATTENDEE_PREFIX);
        $newPublicId  = IdHelper::publicId();

        $updateData = [
            'short_id'  => $newShortId,
            'public_id' => $newPublicId,
        ];

        if ($dto->toIdentifierType === 'email') {
            $updateData['email'] = $dto->toIdentifier;
            $updateData['phone'] = null;
        } else {
            $updateData['phone'] = $dto->toIdentifier;
        }

        // Mutate the attendee record in-place (KYD-style: immediate, no accept flow)
        $this->attendeeRepository->updateWhere(
            attributes: $updateData,
            where: ['id' => $attendee->getId()]
        );

        // Log the transfer in the order audit trail
        $this->orderAuditLogService->logAttendeeUpdate(
            attendee: $attendee,
            oldValues: [
                'email'     => $fromEmail,
                'phone'     => $attendee->getPhone(),
                'short_id'  => $attendee->getShortId(),
                'public_id' => $attendee->getPublicId(),
            ],
            newValues: [
                'email'     => $updateData['email'] ?? $attendee->getEmail(),
                'phone'     => $updateData['phone'] ?? null,
                'short_id'  => $newShortId,
                'public_id' => $newPublicId,
                'action'    => OrderAuditAction::TICKET_TRANSFERRED->value,
            ],
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent,
        );

        // Persist the transfer record
        $this->ticketTransferRepository->create([
            TicketTransferDomainObjectAbstract::ORIGINAL_ATTENDEE_ID => $attendee->getId(),
            TicketTransferDomainObjectAbstract::NEW_ATTENDEE_ID      => $attendee->getId(), // same row, mutated
            TicketTransferDomainObjectAbstract::EVENT_ID             => $attendee->getEventId(),
            TicketTransferDomainObjectAbstract::FROM_EMAIL           => $fromEmail,
            TicketTransferDomainObjectAbstract::TO_IDENTIFIER        => $dto->toIdentifier,
            TicketTransferDomainObjectAbstract::TO_IDENTIFIER_TYPE   => $dto->toIdentifierType,
            TicketTransferDomainObjectAbstract::TOKEN                => bin2hex(random_bytes(32)),
            TicketTransferDomainObjectAbstract::TRANSFERRED_AT       => now()->toDateTimeString(),
        ]);

        // Reload updated attendee with full relations for email
        $updatedAttendee = $this->reloadAttendee($attendee->getId());

        $this->sendNotifications(
            dto: $dto,
            updatedAttendee: $updatedAttendee,
            fromEmail: $fromEmail,
            event: $this->loadEventWithRelations($dto->eventId),
        );

        return $updatedAttendee;
    }

    private function reloadAttendee(int $attendeeId): AttendeeDomainObject
    {
        return $this->attendeeRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, nested: [
                new Relationship(OrderItemDomainObject::class),
            ], name: 'order'))
            ->findById($attendeeId);
    }

    private function loadEventWithRelations(int $eventId): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($eventId);
    }

    private function sendNotifications(
        TransferTicketDTO    $dto,
        AttendeeDomainObject $updatedAttendee,
        string               $fromEmail,
        EventDomainObject    $event,
    ): void {
        // Notify the recipient by email if this is an email transfer
        if ($dto->toIdentifierType === 'email') {
            $this->mailer
                ->to($dto->toIdentifier)
                ->queue(new TicketTransferMail(
                    attendee:      $updatedAttendee,
                    event:         $event,
                    eventSettings: $event->getEventSettings(),
                    organizer:     $event->getOrganizer(),
                    order:         $updatedAttendee->getOrder(),
                ));
        }

        // Always notify the sender that the transfer went through
        $this->mailer
            ->to($fromEmail)
            ->queue(new TicketTransferredSenderMail(
                event:           $event,
                eventSettings:   $event->getEventSettings(),
                organizer:       $event->getOrganizer(),
                toIdentifier:    $dto->toIdentifier,
                toIdentifierType: $dto->toIdentifierType,
            ));
    }
}
