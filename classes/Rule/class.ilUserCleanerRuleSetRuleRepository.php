<?php
declare(strict_types=1);

final class ilUserCleanerRuleSetRuleRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE = 'ucc_rule_set_rule';

    /** @return ilUserCleanerRuleSetRule[] */
    public function getByRuleSetId(int $rule_set_id): array
    {
        return array_map(
            ilUserCleanerRuleSetRule::fromRow(...),
            $this->fetchAll(
                'SELECT * FROM ' . self::TABLE . ' WHERE rule_set_id = %s ORDER BY sequence_no, membership_id',
                ['integer'],
                [$rule_set_id]
            )
        );
    }

    public function insert(int $rule_set_id, int $rule_id, ?int $sequence = null): ilUserCleanerRuleSetRule
    {
        $sequence ??= $this->getNextSequence($rule_set_id);
        $membership = new ilUserCleanerRuleSetRule(
            $this->database->nextId(self::TABLE),
            $rule_set_id,
            $rule_id,
            $sequence
        );
        $this->database->insert(self::TABLE, [
            'membership_id' => ['integer', $membership->id],
            'rule_set_id' => ['integer', $membership->ruleSetId],
            'rule_id' => ['integer', $membership->ruleId],
            'sequence_no' => ['integer', $membership->sequence],
        ]);

        return $membership;
    }

    public function update(ilUserCleanerRuleSetRule $membership): void
    {
        $this->database->update(
            self::TABLE,
            [
                'rule_set_id' => ['integer', $membership->ruleSetId],
                'rule_id' => ['integer', $membership->ruleId],
                'sequence_no' => ['integer', $membership->sequence],
            ],
            ['membership_id' => ['integer', $membership->id]]
        );
    }

    public function delete(int $id): void
    {
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE . ' WHERE membership_id = %s',
            ['integer'],
            [$id]
        );
    }

    private function getNextSequence(int $rule_set_id): int
    {
        $row = $this->fetchRow(
            'SELECT MAX(sequence_no) maximum FROM ' . self::TABLE . ' WHERE rule_set_id = %s',
            ['integer'],
            [$rule_set_id]
        );

        return ((int) ($row['maximum'] ?? 0)) + 1;
    }
}
