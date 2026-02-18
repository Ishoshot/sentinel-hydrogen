<?php

declare(strict_types=1);

use App\Enums\Commands\CommandInputDecision;
use App\Enums\Commands\CommandInputRiskLevel;
use App\Services\Commands\Policies\CommandInputDecisionPolicy;
use App\Services\Commands\ValueObjects\CommandInputClassificationResult;

it('keeps deterministic block decisions even if llm allows', function (): void {
    $policy = app(CommandInputDecisionPolicy::class);

    $rules = new CommandInputClassificationResult(
        decision: CommandInputDecision::Block,
        riskLevel: CommandInputRiskLevel::Critical,
        riskTypes: ['data_exfiltration'],
        confidence: 0.97,
        summary: 'Rules blocked command.',
        signals: [],
    );
    $llm = new CommandInputClassificationResult(
        decision: CommandInputDecision::Allow,
        riskLevel: CommandInputRiskLevel::Low,
        riskTypes: [],
        confidence: 0.55,
        summary: 'LLM considered low risk.',
        signals: [],
    );

    $result = $policy->resolve($rules, $llm);

    expect($result->decision)->toBe(CommandInputDecision::Block)
        ->and($result->riskLevel)->toBe(CommandInputRiskLevel::Critical)
        ->and($result->summary)->toBe('Rules blocked command.');
});

it('escalates to caution when either side marks caution', function (): void {
    $policy = app(CommandInputDecisionPolicy::class);

    $rules = new CommandInputClassificationResult(
        decision: CommandInputDecision::Allow,
        riskLevel: CommandInputRiskLevel::Low,
        riskTypes: [],
        confidence: 0.1,
        summary: 'No issues.',
        signals: [],
    );
    $llm = new CommandInputClassificationResult(
        decision: CommandInputDecision::Caution,
        riskLevel: CommandInputRiskLevel::High,
        riskTypes: ['policy_bypass'],
        confidence: 0.82,
        summary: 'Potential bypass intent.',
        signals: [],
    );

    $result = $policy->resolve($rules, $llm);

    expect($result->decision)->toBe(CommandInputDecision::Caution)
        ->and($result->riskLevel)->toBe(CommandInputRiskLevel::High)
        ->and($result->riskTypes)->toContain('policy_bypass')
        ->and($result->summary)->toBe('Potential bypass intent.');
});
