@if(!empty($untrusted_context))
<<<UNTRUSTED_CONTEXT_START:pull_request>>>
{!! $untrusted_context !!}
<<<UNTRUSTED_CONTEXT_END:pull_request>>>

@endif
## Request

**Command:** {{ $command }}

**Query:** {{ $query }}

@if(!empty($context_hints['files']))
**Files mentioned:** {{ implode(', ', array_map(fn($f) => "`{$f}`", $context_hints['files'])) }}
@endif
@if(!empty($context_hints['symbols']))
**Symbols mentioned:** {{ implode(', ', array_map(fn($s) => "`{$s}`", $context_hints['symbols'])) }}
@endif
@if(!empty($context_hints['lines']))
**Lines referenced:** @foreach($context_hints['lines'] as $line){{ $line['start'] }}@if($line['end'] && $line['end'] !== $line['start'])-{{ $line['end'] }}@endif{{ !$loop->last ? ', ' : '' }}@endforeach

@endif
