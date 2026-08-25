<?php
declare(strict_types=1);

final class ilUserCleanerRuleRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE_RULE = 'ucc_rule';
    private const TABLE_MEMBERSHIP = 'ucc_rule_set_rule';

    /** @return ilUserCleanerRule[] */
    public function getAll(): array
    {
        return array_map(
            ilUserCleanerRule::fromRow(...),
            $this->fetchAll(
                'SELECT r.rule_id, r.parameter_id, p.parameter, r.symbole AS symbol, r.value, ' .
                'p.source_type, p.value_required, p.configuration_required, r.source_config_id ' .
                'FROM ' . self::TABLE_RULE . ' r ' .
                'JOIN ucc_parameter p ON p.parameter_id = r.parameter_id ' .
                'ORDER BY p.parameter, r.symbole, r.value, r.rule_id'
            )
        );
    }

    public function getById(int $id): ?ilUserCleanerRule
    {
        $row = $this->fetchRow(
            'SELECT r.rule_id, r.parameter_id, p.parameter, r.symbole AS symbol, r.value, ' .
            'p.source_type, p.value_required, p.configuration_required, r.source_config_id ' .
            'FROM ' . self::TABLE_RULE . ' r ' .
            'JOIN ucc_parameter p ON p.parameter_id = r.parameter_id ' .
            'WHERE r.rule_id = %s',
            ['integer'],
            [$id]
        );

        return $row === null ? null : ilUserCleanerRule::fromRow($row);
    }

    public function insert(
        int $parameter_id,
        string $symbol,
        int $value,
        ?string $source_config_id = null
    ): ilUserCleanerRule
    {
        $type = $this->getType($parameter_id);
        $rule = new ilUserCleanerRule(
            $this->database->nextId(self::TABLE_RULE),
            $parameter_id,
            $type->key,
            $symbol,
            $value,
            $type->source,
            $type->valueRequired,
            $type->configurationRequired,
            $source_config_id
        );
        $this->database->insert(self::TABLE_RULE, [
            'rule_id' => ['integer', $rule->id],
            'parameter_id' => ['integer', $rule->parameterId],
            'symbole' => ['text', $rule->symbol],
            'value' => ['integer', $rule->value],
            'source_config_id' => ['text', $rule->sourceConfigId],
        ]);

        return $rule;
    }

    public function update(ilUserCleanerRule $rule): void
    {
        $this->getType($rule->parameterId);
        $this->database->update(
            self::TABLE_RULE,
            [
                'parameter_id' => ['integer', $rule->parameterId],
                'symbole' => ['text', $rule->symbol],
                'value' => ['integer', $rule->value],
                'source_config_id' => ['text', $rule->sourceConfigId],
            ],
            ['rule_id' => ['integer', $rule->id]]
        );
    }

    public function delete(int $id): void
    {
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE_MEMBERSHIP . ' WHERE rule_id = %s',
            ['integer'],
            [$id]
        );
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE_RULE . ' WHERE rule_id = %s',
            ['integer'],
            [$id]
        );
    }

    public function deleteIfUnused(int $id): bool
    {
        $row = $this->fetchRow(
            'SELECT COUNT(*) amount FROM ' . self::TABLE_MEMBERSHIP . ' WHERE rule_id = %s',
            ['integer'],
            [$id]
        );
        if ((int) ($row['amount'] ?? 0) > 0) {
            return false;
        }

        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE_RULE . ' WHERE rule_id = %s',
            ['integer'],
            [$id]
        );
        return true;
    }

    public function getType(int $id): ilUserCleanerRuleType
    {
        $row = $this->fetchRow(
            'SELECT parameter_id, parameter, source_type, value_required, configuration_required ' .
            'FROM ucc_parameter WHERE parameter_id = %s',
            ['integer'],
            [$id]
        );
        if ($row === null) {
            throw new OutOfBoundsException('Unknown cleanup parameter: ' . $id);
        }

        return ilUserCleanerRuleType::fromRow($row);
    }

    /** @return ilUserCleanerRuleType[] */
    public function getTypes(): array
    {
        return array_map(
            ilUserCleanerRuleType::fromRow(...),
            $this->fetchAll(
                'SELECT parameter_id, parameter, source_type, value_required, configuration_required ' .
                'FROM ucc_parameter ORDER BY parameter'
            )
        );
    }
}
