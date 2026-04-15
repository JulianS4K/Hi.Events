@php /** @var \HiEvents\DomainObjects\AttendeeDomainObject $attendee */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var string $ticketUrl */ @endphp

<x-mail::message>
# {{ __('You\'ve received a ticket! 🎟️') }}

{{ __('Someone has transferred their ticket for **:event** to you.', ['event' => $event->getTitle()]) }}

**{{ __('Your ticket details') }}**

- **{{ __('Name') }}:** {{ $attendee->getFullName() }}
- **{{ __('Event') }}:** {{ $event->getTitle() }}
@if($event->getStartDate())
- **{{ __('Date') }}:** {{ \HiEvents\Helper\DateHelper::formatDateWithTimezone($event->getStartDate(), $event->getTimezone()) }}
@endif
@if($eventSettings->getLocationDetails())
- **{{ __('Location') }}:** {{ $eventSettings->getLocationDetails() }}
@endif

<x-mail::button :url="$ticketUrl">
{{ __('View Your Ticket') }}
</x-mail::button>

{{ __('If you have any questions, please contact the event organizer at') }} <a href="mailto:{{ $eventSettings->getSupportEmail() }}">{{ $eventSettings->getSupportEmail() }}</a>.

{{ __('See you there,') }}<br>
{{ $organizer->getName() ?: config('app.name') }}
</x-mail::message>
