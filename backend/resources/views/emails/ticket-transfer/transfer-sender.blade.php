@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var string $toIdentifier */ @endphp
@php /** @var string $toIdentifierType */ @endphp

<x-mail::message>
# {{ __('Your ticket has been transferred') }}

{{ __('Your ticket for **:event** has been successfully transferred.', ['event' => $event->getTitle()]) }}

**{{ __('Transfer details') }}**

- **{{ __('Transferred to') }}:** {{ $toIdentifierType === 'phone' ? __('Phone number ending ') . substr($toIdentifier, -4) : $toIdentifier }}
- **{{ __('Event') }}:** {{ $event->getTitle() }}
@if($event->getStartDate())
- **{{ __('Date') }}:** {{ \HiEvents\Helper\DateHelper::formatDateWithTimezone($event->getStartDate(), $event->getTimezone()) }}
@endif

{{ __('This transfer is final. The recipient now holds this ticket and it has been removed from your account.') }}

{{ __('If you did not initiate this transfer, please contact the event organizer immediately at') }} <a href="mailto:{{ $eventSettings->getSupportEmail() }}">{{ $eventSettings->getSupportEmail() }}</a>.

{{ __('Thanks,') }}<br>
{{ $organizer->getName() ?: config('app.name') }}
</x-mail::message>
