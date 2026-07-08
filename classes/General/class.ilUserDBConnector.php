<?php
declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ********************************************************************
 */

/**
 * @author Ulf Bischoff <ulf.bischoff@tik.uni-stuttgart.de>
 */
class ilUserDBConnector
{
    private const TABLE_CONFIG = 'ucc_config';
    private const TABLE_RULE = 'ucc_rule';
    private const TABLE_PARAMETER = 'ucc_parameter';
    private const TABLE_AUTH = 'ucc_auth';
    private const TABLE_EXECUTION_RULES = 'ucc_execution_rules';
    private const TABLE_EXCLUSION = 'ucc_exclusion';

    private ilDBInterface $ilDB;

    public function __construct()
    {
        global $DIC, $ilDB;

        $this->ilDB = isset($DIC) ? $DIC->database() : $ilDB;
    }

    public function getConfigValue(string $key): ?string
    {
        $key_column = $this->ilDB->quoteIdentifier('key');
        $row = $this->fetchRow(
            'SELECT value FROM ' . self::TABLE_CONFIG . ' WHERE ' . $key_column . ' = %s',
            ['string'],
            [$key]
        );

        return $row['value'] ?? null;
    }

    public function getConfig(): array
    {
        return $this->fetchAll(
            'SELECT * FROM ' . self::TABLE_CONFIG . ' ORDER BY ' . $this->ilDB->quoteIdentifier('key')
        );
    }

    public function setConfigValue(string $key, string $value): void
    {
        $where = ['key' => ['text', $key]];
        $values = [
            'key' => ['text', $key],
            'value' => ['clob', $value],
        ];

        if ($this->getConfigValue($key) === null) {
            $this->ilDB->insert(
                self::TABLE_CONFIG,
                ['config_id' => ['integer', $this->ilDB->nextId(self::TABLE_CONFIG)]] + $values
            );
            return;
        }

        $this->ilDB->update(self::TABLE_CONFIG, $values, $where);
    }

    public function deleteConfigValue(string $key): void
    {
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_CONFIG . ' WHERE ' . $this->ilDB->quoteIdentifier('key') . ' = %s',
            ['string'],
            [$key]
        );
    }

    public function getParameters(): array
    {
        return $this->fetchAll('SELECT * FROM ' . self::TABLE_PARAMETER . ' ORDER BY parameter');
    }

    public function getParameterById(int $parameter_id): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_PARAMETER . ' WHERE parameter_id = %s',
            ['integer'],
            [$parameter_id]
        ) ?? [];
    }

    public function getParameterByName(string $parameter): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_PARAMETER . ' WHERE parameter = %s',
            ['string'],
            [$parameter]
        ) ?? [];
    }

    public function insertParameter(string $parameter): int
    {
        $parameter_id = $this->ilDB->nextId(self::TABLE_PARAMETER);
        $this->ilDB->insert(self::TABLE_PARAMETER, [
            'parameter_id' => ['integer', $parameter_id],
            'parameter' => ['text', $parameter],
        ]);

        return $parameter_id;
    }

    public function updateParameter(int $parameter_id, string $parameter): void
    {
        $this->ilDB->update(
            self::TABLE_PARAMETER,
            ['parameter' => ['text', $parameter]],
            ['parameter_id' => ['integer', $parameter_id]]
        );
    }

    public function deleteParameter(int $parameter_id): void
    {
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_PARAMETER . ' WHERE parameter_id = %s',
            ['integer'],
            [$parameter_id]
        );
    }

    public function getRules(): array
    {
        return $this->fetchAll('SELECT * FROM ' . self::TABLE_RULE . ' ORDER BY rule_id');
    }

    public function getRulesWithParameters(): array
    {
        return $this->fetchAll(
            'SELECT r.rule_id, r.parameter_id, p.parameter, r.symbole, r.value ' .
            'FROM ' . self::TABLE_RULE . ' r ' .
            'JOIN ' . self::TABLE_PARAMETER . ' p ON p.parameter_id = r.parameter_id ' .
            'ORDER BY p.parameter, r.symbole, r.value'
        );
    }

    public function getRuleById(int $rule_id): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_RULE . ' WHERE rule_id = %s',
            ['integer'],
            [$rule_id]
        ) ?? [];
    }

    public function getRulesByParameterId(int $parameter_id): array
    {
        return $this->fetchAll(
            'SELECT * FROM ' . self::TABLE_RULE . ' WHERE parameter_id = %s ORDER BY rule_id',
            ['integer'],
            [$parameter_id]
        );
    }

    public function insertRule(int $parameter_id, string $symbole, int $value): int
    {
        $rule_id = $this->ilDB->nextId(self::TABLE_RULE);
        $this->ilDB->insert(self::TABLE_RULE, [
            'rule_id' => ['integer', $rule_id],
            'parameter_id' => ['integer', $parameter_id],
            'symbole' => ['text', $symbole],
            'value' => ['integer', $value],
        ]);

        return $rule_id;
    }

    public function updateRule(int $rule_id, int $parameter_id, string $symbole, int $value): void
    {
        $this->ilDB->update(
            self::TABLE_RULE,
            [
                'parameter_id' => ['integer', $parameter_id],
                'symbole' => ['text', $symbole],
                'value' => ['integer', $value],
            ],
            ['rule_id' => ['integer', $rule_id]]
        );
    }

    public function deleteRule(int $rule_id): void
    {
        $this->deleteExecutionRulesByRuleId($rule_id);
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_RULE . ' WHERE rule_id = %s',
            ['integer'],
            [$rule_id]
        );
    }

    public function deleteExecutionRulesByRuleId(int $rule_id): void
    {
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE rule_id = %s',
            ['integer'],
            [$rule_id]
        );
    }



    public function getGlobalRoles(): array
    {
        return $this->fetchAll(
            'SELECT od.obj_id, od.title, od.description, fa.rol_id, fa.parent ' .
            'FROM object_data od ' .
            'JOIN rbac_fa fa ON fa.rol_id = od.obj_id ' .
            'WHERE od.type = %s ' .
            'AND fa.parent = %s ' .
            'AND fa.assign = %s ' .
            'ORDER BY od.title',
            ['text', 'integer', 'text'],
            ['role', ROLE_FOLDER_ID, 'y']
        );
    }

    public function getAuthModes(): array
    {
        return $this->fetchAll('SELECT * FROM ' . self::TABLE_AUTH . ' ORDER BY auth_mode');
    }

    public function getAuthById(int $auth_id): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_AUTH . ' WHERE auth_id = %s',
            ['integer'],
            [$auth_id]
        ) ?? [];
    }

    public function getAuthByMode(string $auth_mode): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_AUTH . ' WHERE auth_mode = %s',
            ['string'],
            [$auth_mode]
        ) ?? [];
    }

    public function insertAuthMode(string $auth_mode): int
    {
        $auth_id = $this->ilDB->nextId(self::TABLE_AUTH);
        $this->ilDB->insert(self::TABLE_AUTH, [
            'auth_id' => ['integer', $auth_id],
            'auth_mode' => ['text', $auth_mode],
        ]);

        return $auth_id;
    }

    public function updateAuthMode(int $auth_id, string $auth_mode): void
    {
        $this->ilDB->update(
            self::TABLE_AUTH,
            ['auth_mode' => ['text', $auth_mode]],
            ['auth_id' => ['integer', $auth_id]]
        );
    }

    public function deleteAuthMode(int $auth_id): void
    {
        $this->deleteExecutionRulesByAuthId($auth_id);
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_AUTH . ' WHERE auth_id = %s',
            ['integer'],
            [$auth_id]
        );
    }

    public function authModeExists(string $auth_mode): bool
    {
        return $this->getAuthByMode($auth_mode) !== [];
    }

    public function getExecutionRules(): array
    {
        return $this->fetchAll('SELECT * FROM ' . self::TABLE_EXECUTION_RULES . ' ORDER BY execution_id');
    }

    public function getExecutionRulesWithDetails(): array
    {
        return $this->fetchAll(
            'SELECT er.execution_id, er.rule_id, er.auth_id, er.role_id, ' .
            'p.parameter, r.symbole, r.value, a.auth_mode, od.title role_title ' .
            'FROM ' . self::TABLE_EXECUTION_RULES . ' er ' .
            'JOIN ' . self::TABLE_RULE . ' r ON r.rule_id = er.rule_id ' .
            'JOIN ' . self::TABLE_PARAMETER . ' p ON p.parameter_id = r.parameter_id ' .
            'JOIN ' . self::TABLE_AUTH . ' a ON a.auth_id = er.auth_id ' .
            'LEFT JOIN object_data od ON od.obj_id = er.role_id ' .
            'ORDER BY p.parameter, r.symbole, r.value, a.auth_mode, od.title'
        );
    }

    public function getExecutionRuleById(int $execution_id): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE execution_id = %s',
            ['integer'],
            [$execution_id]
        ) ?? [];
    }

    public function getExecutionRulesByRuleId(int $rule_id): array
    {
        return $this->fetchAll(
            'SELECT * FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE rule_id = %s ORDER BY execution_id',
            ['integer'],
            [$rule_id]
        );
    }

    public function getExecutionRulesByAuthId(int $auth_id): array
    {
        return $this->fetchAll(
            'SELECT * FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE auth_id = %s ORDER BY execution_id',
            ['integer'],
            [$auth_id]
        );
    }

    public function deleteExecutionRulesByAuthId(int $auth_id): void
    {
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE auth_id = %s',
            ['integer'],
            [$auth_id]
        );
    }

    public function getExecutionRulesByRoleId(int $role_id): array
    {
        return $this->fetchAll(
            'SELECT * FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE role_id = %s ORDER BY execution_id',
            ['integer'],
            [$role_id]
        );
    }

    public function getExecutionRuleByCombination(int $rule_id, int $auth_id, int $role_id): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE rule_id = %s AND auth_id = %s AND role_id = %s',
            ['integer', 'integer', 'integer'],
            [$rule_id, $auth_id, $role_id]
        ) ?? [];
    }

    public function executionRuleExists(int $rule_id, int $auth_id, int $role_id): bool
    {
        return $this->getExecutionRuleByCombination($rule_id, $auth_id, $role_id) !== [];
    }

    public function insertExecutionRule(int $rule_id, int $auth_id, int $role_id): int
    {
        $execution_id = $this->ilDB->nextId(self::TABLE_EXECUTION_RULES);
        $this->ilDB->insert(self::TABLE_EXECUTION_RULES, [
            'execution_id' => ['integer', $execution_id],
            'rule_id' => ['integer', $rule_id],
            'auth_id' => ['integer', $auth_id],
            'role_id' => ['integer', $role_id],
        ]);

        return $execution_id;
    }

    public function updateExecutionRule(int $execution_id, int $rule_id, int $auth_id, int $role_id): void
    {
        $this->ilDB->update(
            self::TABLE_EXECUTION_RULES,
            [
                'rule_id' => ['integer', $rule_id],
                'auth_id' => ['integer', $auth_id],
                'role_id' => ['integer', $role_id],
            ],
            ['execution_id' => ['integer', $execution_id]]
        );
    }

    public function deleteExecutionRule(int $execution_id): void
    {
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_EXECUTION_RULES . ' WHERE execution_id = %s',
            ['integer'],
            [$execution_id]
        );
    }

    public function getExclusions(): array
    {
        return $this->fetchAll('SELECT * FROM ' . self::TABLE_EXCLUSION . ' ORDER BY exclusion_id');
    }

    public function getExclusionById(int $exclusion_id): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_EXCLUSION . ' WHERE exclusion_id = %s',
            ['integer'],
            [$exclusion_id]
        ) ?? [];
    }

    public function getExclusionByUserId(int $user_id): array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TABLE_EXCLUSION . ' WHERE user_id = %s',
            ['integer'],
            [$user_id]
        ) ?? [];
    }

    public function isUserExcluded(int $user_id): bool
    {
        return $this->getExclusionByUserId($user_id) !== [];
    }

    public function insertExclusion(int $user_id): int
    {
        $exclusion_id = $this->ilDB->nextId(self::TABLE_EXCLUSION);
        $this->ilDB->insert(self::TABLE_EXCLUSION, [
            'exclusion_id' => ['integer', $exclusion_id],
            'user_id' => ['integer', $user_id],
        ]);

        return $exclusion_id;
    }

    public function updateExclusion(int $exclusion_id, int $user_id): void
    {
        $this->ilDB->update(
            self::TABLE_EXCLUSION,
            ['user_id' => ['integer', $user_id]],
            ['exclusion_id' => ['integer', $exclusion_id]]
        );
    }

    public function deleteExclusion(int $exclusion_id): void
    {
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_EXCLUSION . ' WHERE exclusion_id = %s',
            ['integer'],
            [$exclusion_id]
        );
    }

    public function deleteExclusionByUserId(int $user_id): void
    {
        $this->manipulate(
            'DELETE FROM ' . self::TABLE_EXCLUSION . ' WHERE user_id = %s',
            ['integer'],
            [$user_id]
        );
    }

    private function fetchRow(string $sql, array $types = [], array $values = []): ?array
    {
        $result = $types === []
            ? $this->ilDB->query($sql)
            : $this->ilDB->queryF($sql, $types, $values);
        $row = $this->ilDB->fetchAssoc($result);

        return $row;
    }

    private function fetchAll(string $sql, array $types = [], array $values = []): array
    {
        $result = $types === []
            ? $this->ilDB->query($sql)
            : $this->ilDB->queryF($sql, $types, $values);

        return $this->ilDB->fetchAll($result);
    }

    private function manipulate(string $sql, array $types, array $values): void
    {
        $this->ilDB->manipulateF($sql, $types, $values);
    }
}
