<?php

declare(strict_types=1);

namespace App\Services\Commands\Policies;

use App\Enums\Commands\CommandInputDecision;
use App\Enums\Commands\CommandInputRiskLevel;
use App\Services\Commands\ValueObjects\CommandInputClassificationResult;
use App\Services\Commands\ValueObjects\CommandInputClassificationSignal;

final class CommandInputRulesPolicy
{
    /**
     * @var array<int, array{
     *     code: string,
     *     pattern: string,
     *     risk_type: string,
     *     severity: CommandInputRiskLevel,
     *     decision: CommandInputDecision
     * }>
     */
    private const array RULES = [
        [
            'code' => 'PROMPT_INJECTION_OVERRIDE_ATTEMPT',
            'pattern' => '/\b(ignore|disregard|bypass)\b.{0,40}\b(instruction|system prompt|developer message|safety|guardrail)\b/i',
            'risk_type' => 'prompt_injection',
            'severity' => CommandInputRiskLevel::Medium,
            'decision' => CommandInputDecision::Caution,
        ],
        [
            'code' => 'SECRETS_EXFILTRATION_REQUEST',
            'pattern' => '/\b(show|print|dump|reveal|leak|exfiltrat|export)\b.{0,120}(secrets?|tokens?|api[_\\- ]?keys?|passwords?|credentials?|private keys?|\\.env|sessions?|cookies?)/i',
            'risk_type' => 'data_exfiltration',
            'severity' => CommandInputRiskLevel::Critical,
            'decision' => CommandInputDecision::Block,
        ],
        [
            'code' => 'MALICIOUS_EXPLOIT_INTENT',
            'pattern' => '/\b(write|generate|craft|build)\b.{0,80}\b(exploit|payload|malware|ransomware|backdoor|reverse shell|keylogger|sql injection|xss|command injection)\b/i',
            'risk_type' => 'malicious_code_intent',
            'severity' => CommandInputRiskLevel::Critical,
            'decision' => CommandInputDecision::Block,
        ],
        [
            'code' => 'AUTHORIZATION_BYPASS_REQUEST',
            'pattern' => '/\b(disable|remove|bypass|skip)\b.{0,80}\b(auth|authorization|permission|policy|guard|rate limit|csrf)\b/i',
            'risk_type' => 'policy_bypass',
            'severity' => CommandInputRiskLevel::High,
            'decision' => CommandInputDecision::Caution,
        ],
        [
            'code' => 'RAW_SECRET_MATERIAL_PRESENT',
            'pattern' => '/(ghp_[a-zA-Z0-9]{20,}|sk-(?:ant|proj|live|test)-[a-zA-Z0-9\\-_]{10,}|-----BEGIN(?: RSA)? PRIVATE KEY-----)/i',
            'risk_type' => 'secret_exposure',
            'severity' => CommandInputRiskLevel::High,
            'decision' => CommandInputDecision::Block,
        ],
    ];

    /**
     * Classify user input via deterministic security rules.
     */
    public function classify(string $query): CommandInputClassificationResult
    {
        $signals = [];
        $riskTypes = [];
        $decision = CommandInputDecision::Allow;
        $riskLevel = CommandInputRiskLevel::Low;

        foreach (self::RULES as $rule) {
            $matchedEvidence = $this->matchEvidence($query, $rule['pattern']);

            if ($matchedEvidence === null) {
                continue;
            }

            $signals[] = new CommandInputClassificationSignal(
                source: 'rule',
                code: $rule['code'],
                severity: $rule['severity'],
                evidence: $matchedEvidence
            );
            $riskTypes[] = $rule['risk_type'];
            $riskLevel = CommandInputRiskLevel::max($riskLevel, $rule['severity']);

            if ($rule['decision']->priority() > $decision->priority()) {
                $decision = $rule['decision'];
            }
        }

        if ($signals === []) {
            return CommandInputClassificationResult::allow();
        }

        return new CommandInputClassificationResult(
            decision: $decision,
            riskLevel: $riskLevel,
            riskTypes: array_values(array_unique($riskTypes)),
            confidence: $this->confidenceFor($decision, $signals),
            summary: $this->summaryFor($decision, $riskLevel, $riskTypes),
            signals: $signals,
        );
    }

    /**
     * Resolve the first matching evidence snippet for a regex.
     */
    private function matchEvidence(string $query, string $pattern): ?string
    {
        if (! preg_match($pattern, $query, $matches)) {
            return null;
        }

        $evidence = is_string($matches[0] ?? null) ? $matches[0] : '';
        if ($evidence === '') {
            return null;
        }

        return mb_substr($evidence, 0, 180);
    }

    /**
     * @param  array<int, CommandInputClassificationSignal>  $signals
     */
    private function confidenceFor(CommandInputDecision $decision, array $signals): float
    {
        $base = match ($decision) {
            CommandInputDecision::Block => 0.95,
            CommandInputDecision::Caution => 0.75,
            CommandInputDecision::Allow => 0.0,
        };

        $signalBoost = min(0.04, max(0, count($signals) - 1) * 0.02);

        return min(1.0, $base + $signalBoost);
    }

    /**
     * @param  array<int, string>  $riskTypes
     */
    private function summaryFor(
        CommandInputDecision $decision,
        CommandInputRiskLevel $riskLevel,
        array $riskTypes
    ): string {
        if ($decision === CommandInputDecision::Allow) {
            return 'No elevated risk patterns detected.';
        }

        $types = $riskTypes === [] ? 'unknown risk type' : implode(', ', array_values(array_unique($riskTypes)));

        return sprintf(
            'Deterministic rules detected %s risk (%s): %s.',
            $riskLevel->value,
            $decision->value,
            $types
        );
    }
}
