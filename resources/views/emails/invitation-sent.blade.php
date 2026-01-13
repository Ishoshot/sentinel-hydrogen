<x-mail::message>
<img src="{{ asset('images/sentinel-logo-wordmark.png') }}" alt="Sentinel" style="height: 40px; margin-bottom: 24px;">

# You've Been Invited

**{{ $invitation->invitedBy?->name ?? 'Someone' }}** has invited you to join **{{ $invitation->workspace?->name ?? 'a workspace' }}** as a **{{ $invitation->role->label() }}**.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invitation will expire in 7 days.

If you did not expect this invitation, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
