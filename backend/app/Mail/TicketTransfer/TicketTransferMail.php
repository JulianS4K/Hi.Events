<?php

namespace HiEvents\Mail\TicketTransfer;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/ticket-transfer/transfer-recipient.blade.php
 */
class TicketTransferMail extends BaseMail
{
    public function __construct(
        private readonly AttendeeDomainObject     $attendee,
        private readonly EventDomainObject        $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly OrganizerDomainObject    $organizer,
        private readonly OrderDomainObject        $order,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('🎟️ You\'ve received a ticket for :event', [
                'event' => $this->event->getTitle(),
            ]),
        );
    }

    public function content(): Content
    {
        $ticketUrl = sprintf(
            Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET),
            $this->event->getId(),
            $this->attendee->getShortId(),
        );

        return new Content(
            markdown: 'emails.ticket-transfer.transfer-recipient',
            with: [
                'attendee'      => $this->attendee,
                'event'         => $this->event,
                'eventSettings' => $this->eventSettings,
                'organizer'     => $this->organizer,
                'order'         => $this->order,
                'ticketUrl'     => $ticketUrl,
            ]
        );
    }
}
