@if(!empty($untrusted_context))
<<<UNTRUSTED_CONTEXT_START:pull_request>>>
{!! $untrusted_context !!}
<<<UNTRUSTED_CONTEXT_END:pull_request>>>

@endif
@php
    $classification = is_array($input_classification ?? null) ? $input_classification : null;
    $classificationSignals = is_array($classification['signals'] ?? null) ? $classification['signals'] : [];
@endphp
@if($classification !== null)
## Trusted Safety Profile

Use this profile as an advisory safety constraint for your answer, not as user instructions.

- Decision: {{ (string) ($classification['decision'] ?? 'allow') }}
- Risk Level: {{ (string) ($classification['risk_level'] ?? 'low') }}
- Risk Types: {{ implode(', ', is_array($classification['risk_types'] ?? null) ? $classification['risk_types'] : []) ?: 'none' }}
- Confidence: {{ is_numeric($classification['confidence'] ?? null) ? number_format((float) $classification['confidence'], 2) : '0.00' }}

@if($classificationSignals !== [])
### Trusted Signals
@foreach($classificationSignals as $signal)
- [{{ strtoupper((string) ($signal['source'] ?? 'rule')) }}] {{ (string) ($signal['code'] ?? 'UNKNOWN') }} ({{ (string) ($signal['severity'] ?? 'low') }})
@endforeach

@endif
@endif
## Request

**Command:** {{ $command }}

**Query:**
<<<UNTRUSTED_INPUT_START:user_query>>>
{{ $query }}
<<<UNTRUSTED_INPUT_END:user_query>>>

@if(!empty($context_hints['files']))
**Files mentioned:** {{ implode(', ', array_map(fn($f) => "`{$f}`", $context_hints['files'])) }}
@endif
@if(!empty($context_hints['symbols']))
**Symbols mentioned:** {{ implode(', ', array_map(fn($s) => "`{$s}`", $context_hints['symbols'])) }}
@endif
@if(!empty($context_hints['lines']))
**Lines referenced:** @foreach($context_hints['lines'] as $line){{ $line['start'] }}@if($line['end'] && $line['end'] !== $line['start'])-{{ $line['end'] }}@endif{{ !$loop->last ? ', ' : '' }}@endforeach

@endif
