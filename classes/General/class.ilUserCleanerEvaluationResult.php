<?php
declare(strict_types=1);

final class ilUserCleanerEvaluationResult
{
    /**
     * @param array<int, int[]> $matchedRuleSetIdsByUserId
     * @param int[] $unsupportedRuleSetIds
     * @param array<int, array<int, array<int, bool>>> $ruleDiagnosticsByUserId
     */
    public function __construct(
        public readonly array $matchedRuleSetIdsByUserId,
        public readonly int $enabledRuleSetCount,
        public readonly array $unsupportedRuleSetIds,
        public readonly array $ruleDiagnosticsByUserId = []
    ) {
    }

    public function getMatchedUserCount(): int
    {
        return count($this->matchedRuleSetIdsByUserId);
    }
}
