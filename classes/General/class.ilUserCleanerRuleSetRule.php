<?php
declare(strict_types=1);

final class ilUserCleanerRuleSetRule
{
    public function __construct(
        public readonly int $id,
        public readonly int $ruleSetId,
        public readonly int $ruleId,
        public readonly int $sequence
    ) {
        if ($this->id < 0 || $this->ruleSetId <= 0 || $this->ruleId <= 0) {
            throw new InvalidArgumentException('A rule-set membership requires valid related IDs.');
        }
        if ($this->sequence < 0) {
            throw new InvalidArgumentException('A rule-set sequence cannot be negative.');
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['membership_id'],
            (int) $row['rule_set_id'],
            (int) $row['rule_id'],
            (int) $row['sequence_no']
        );
    }
}
