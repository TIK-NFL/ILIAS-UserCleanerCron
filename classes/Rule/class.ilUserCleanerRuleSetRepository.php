<?php
declare(strict_types=1);

final class ilUserCleanerRuleSetRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE_RULE_SET = 'ucc_rule_set';
    private const TABLE_MEMBERSHIP = 'ucc_rule_set_rule';

    /** @return ilUserCleanerRuleSet[] */
    public function getAll(): array
    {
        return array_map(
            ilUserCleanerRuleSet::fromRow(...),
            $this->fetchAll('SELECT * FROM ' . self::TABLE_RULE_SET . ' ORDER BY title, rule_set_id')
        );
    }

    public function getById(int $id): ?ilUserCleanerRuleSet
    {
        $row = $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_RULE_SET . ' WHERE rule_set_id = %s',
            ['integer'],
            [$id]
        );

        return $row === null ? null : ilUserCleanerRuleSet::fromRow($row);
    }

    public function insert(
        string $title,
        string $description,
        int $role_id,
        int $auth_id,
        bool $enabled = true
    ): ilUserCleanerRuleSet
    {
        $id = $this->database->nextId(self::TABLE_RULE_SET);
        $rule_set = new ilUserCleanerRuleSet($id, trim($title), $description, $role_id, $auth_id, $enabled);
        $this->database->insert(self::TABLE_RULE_SET, [
            'rule_set_id' => ['integer', $rule_set->id],
            'title' => ['text', mb_substr($rule_set->title, 0, 255)],
            'description' => ['clob', $rule_set->description],
            'role_id' => ['integer', $rule_set->roleId],
            'auth_id' => ['integer', $rule_set->authId],
            'enabled' => ['integer', (int) $rule_set->enabled],
        ]);

        return $rule_set;
    }

    public function update(ilUserCleanerRuleSet $rule_set): void
    {
        if ($rule_set->id <= 0) {
            throw new InvalidArgumentException('A persisted rule set requires a positive ID.');
        }
        $this->database->update(
            self::TABLE_RULE_SET,
            [
                'title' => ['text', mb_substr(trim($rule_set->title), 0, 255)],
                'description' => ['clob', $rule_set->description],
                'role_id' => ['integer', $rule_set->roleId],
                'auth_id' => ['integer', $rule_set->authId],
                'enabled' => ['integer', (int) $rule_set->enabled],
            ],
            ['rule_set_id' => ['integer', $rule_set->id]]
        );
    }

    public function delete(int $id): void
    {
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE_MEMBERSHIP . ' WHERE rule_set_id = %s',
            ['integer'],
            [$id]
        );
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE_RULE_SET . ' WHERE rule_set_id = %s',
            ['integer'],
            [$id]
        );
    }
}
