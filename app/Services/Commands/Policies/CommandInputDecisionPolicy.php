<?php

declare(strict_types=1);

namespace App\Services\Commands\Policies;

use App\Enums\Commands\CommandInputDecision;
use App\Enums\Commands\CommandInputRiskLevel;
use App\Services\Commands\ValueObjects\CommandInputClassificationResult;

final class CommandInputDecisionPolicy
{
    /**
     * Merge deterministic-rule and LLM assessments into one decision.
     */
    public function resolve(
        CommandInputClassificationResult $rulesAssessment,
        ?CommandInputClassificationResult $llmAssessment,
    ): CommandInputClassificationResult {
        if (! $llmAssessment instanceof CommandInputClassificationResult) {
            return $rulesAssessment;
        }

        $decision = $this->resolveDecision($rulesAssessment, $llmAssessment);
        $riskLevel = CommandInputRiskLevel::max($rulesAssessment->riskLevel, $llmAssessment->riskLevel);
        $riskTypes = array_values(array_unique(array_merge($rulesAssessment->riskTypes, $llmAssessment->riskTypes)));
        $confidence = max($rulesAssessment->confidence, $llmAssessment->confidence);
        $signals = array_merge($rulesAssessment->signals, $llmAssessment->signals);
        $summary = $this->resolveSummary($decision, $rulesAssessment, $llmAssessment);

        return new CommandInputClassificationResult(
            decision: $decision,
            riskLevel: $riskLevel,
            riskTypes: $riskTypes,
            confidence: $confidence,
            summary: $summary,
            signals: $signals,
        );
    }

    /**
     * Resolve final decision with hard-rule precedence.
     */
    private function resolveDecision(
        CommandInputClassificationResult $rulesAssessment,
        CommandInputClassificationResult $llmAssessment,
    ): CommandInputDecision {
        if ($rulesAssessment->decision === CommandInputDecision::Block) {
            return CommandInputDecision::Block;
        }

        if ($llmAssessment->decision === CommandInputDecision::Block) {
            return CommandInputDecision::Block;
        }

        if (
            $rulesAssessment->decision === CommandInputDecision::Caution
            || $llmAssessment->decision === CommandInputDecision::Caution
        ) {
            return CommandInputDecision::Caution;
        }

        return CommandInputDecision::Allow;
    }

    /**
     * Pick an explanatory summary based on final decision.
     */
    private function resolveSummary(
        CommandInputDecision $decision,
        CommandInputClassificationResult $rulesAssessment,
        CommandInputClassificationResult $llmAssessment,
    ): string {
        if ($decision === CommandInputDecision::Block) {
            if ($rulesAssessment->decision === CommandInputDecision::Block) {
                return $rulesAssessment->summary;
            }

            return $llmAssessment->summary;
        }

        if ($decision === CommandInputDecision::Caution) {
            if ($llmAssessment->decision === CommandInputDecision::Caution) {
                return $llmAssessment->summary;
            }

            return $rulesAssessment->summary;
        }

        return 'No elevated risk patterns detected.';
    }
}
