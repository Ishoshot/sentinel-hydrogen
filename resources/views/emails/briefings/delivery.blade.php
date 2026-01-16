<x-mail::message>
# {{ $briefing->title ?? 'Your Briefing is Ready' }}

@if($workspace)
**{{ $workspace->name }}**
@endif

@if(isset($excerpts['email']))
{{ $excerpts['email'] }}
@elseif($narrative)
{{ Str::limit($narrative, 500) }}
@else
Your briefing has been generated and is ready to view.
@endif

@if(!empty($achievements))
## Highlights

@foreach($achievements as $achievement)
- **{{ $achievement['title'] ?? 'Achievement' }}**: {{ $achievement['description'] ?? '' }}
@endforeach
@endif

<x-mail::button :url="config('app.url') . '/briefings/' . $generation->id">
View Full Briefing
</x-mail::button>

---

*Generated {{ $generation->created_at?->format('F j, Y \a\t g:i A') ?? now()->format('F j, Y \a\t g:i A') }}*

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
