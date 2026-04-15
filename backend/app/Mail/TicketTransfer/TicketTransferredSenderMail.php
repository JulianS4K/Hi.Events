<?php

namespace HiEvents\Mail\TicketTransfer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/ticket-transfer/transfer-sender.blade.php
 */
class TicketTransferredSenderMail extends BaseMail
{
    public function __construct(
        private readonly EventDomainObject        $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly OrganizerDomainObject    $organizer,
        private readonly string                   $toIdentifier,
        private readonly string                   $toIdentifierType,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('Your ticket for :event has been transferred', [
                'event' => $this->event->getTitle(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ticket-transfer.transfer-sender',
            with: [
                'event'            => $this->event,
                'eventSettings'    => $this->eventSettings,
                'organizer'        => $this->organizer,
                'toIdentifier'     => $this->toIdentifier,
                'toIdentifierType' => $this->toIdentifierType,
            ]
        );
    }
}
