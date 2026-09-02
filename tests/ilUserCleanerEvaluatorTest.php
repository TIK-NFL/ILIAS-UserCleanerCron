<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerEvaluatorTest extends TestCase
{
    // The real evaluator combines rules with AND, sets with OR, filters roles/auth modes,
    // and protects the actor, administrators, and explicit exclusions.
    public function testCompleteEvaluationAcrossRuleSetsAndUsers(): void
    {
        $sets = [
            new ilUserCleanerRuleSet(1, 'Old staff', '', 10, 1, true),
            new ilUserCleanerRuleSet(2, 'Abandoned guests', '', 20, 1, true),
            new ilUserCleanerRuleSet(3, 'Disabled', '', 10, 1, false),
        ];
        $rules = [
            1 => [
                $this->rule(1, 'account_has_logged_in'),
                $this->rule(2, 'last_login_days', '>', 200),
            ],
            2 => [
                $this->rule(3, 'account_never_logged_in'),
                $this->rule(4, 'account_age_months', '>=', 6),
            ],
            3 => [$this->rule(5, 'last_login_days', '>', 1)],
        ];
        $users = [
            100 => new ilUserCleanerEvaluationUser(100, 'local', '2025-01-01 00:00:00', '2024-01-01 00:00:00', '', 'old-staff'),
            101 => new ilUserCleanerEvaluationUser(101, 'local', '2026-08-01 00:00:00', '2024-01-01 00:00:00', '', 'active-staff'),
            102 => new ilUserCleanerEvaluationUser(102, 'local', null, '2025-01-01 00:00:00', '', 'abandoned-guest'),
            103 => new ilUserCleanerEvaluationUser(103, 'local', null, '2026-07-01 00:00:00', '', 'new-guest'),
            104 => new ilUserCleanerEvaluationUser(104, 'ldap_1', '2025-01-01 00:00:00', '2024-01-01 00:00:00', '', 'wrong-auth'),
            105 => new ilUserCleanerEvaluationUser(105, 'local', '2025-01-01 00:00:00', '2024-01-01 00:00:00', '', 'excluded'),
            106 => new ilUserCleanerEvaluationUser(106, 'local', '2025-01-01 00:00:00', '2024-01-01 00:00:00', '', 'administrator'),
            107 => new ilUserCleanerEvaluationUser(107, 'local', '2025-01-01 00:00:00', '2024-01-01 00:00:00', '', 'actor'),
        ];
        $data = new ilUserCleanerTestEvaluationData(
            $sets,
            $rules,
            [1 => 'local'],
            [10 => [100, 101, 104, 105, 106, 107], 20 => [102, 103]],
            $users,
            107,
            [106],
            [105]
        );

        $result = (new ilUserCleanerEvaluator($data, new ilUserCleanerTestLDAPLookup()))
            ->evaluate(new DateTimeImmutable('2026-08-21 00:00:00'));

        self::assertSame([100 => [1], 102 => [2]], $result->matchedRuleSetIdsByUserId);
        self::assertSame(2, $result->enabledRuleSetCount);
        self::assertSame([], $result->unsupportedRuleSetIds);
        self::assertSame([1 => true, 2 => true], $result->ruleDiagnosticsByUserId[100][1]);
        self::assertSame([3 => true, 4 => true], $result->ruleDiagnosticsByUserId[102][2]);
    }

    // A required but unavailable LDAP source aborts evaluation before any account lookup.
    public function testUnavailableRequiredLdapSourceFailsEvaluation(): void
    {
        $set = new ilUserCleanerRuleSet(9, 'LDAP cleanup', '', 30, 2, true);
        $rule = $this->rule(
            9,
            'external_account_missing_ldap',
            '=',
            1,
            ilUserCleanerRuleSource::LDAP,
            'ldap:77'
        );
        $data = new ilUserCleanerTestEvaluationData(
            [$set],
            [9 => [$rule]],
            [2 => 'ldap'],
            [30 => [200]],
            [200 => new ilUserCleanerEvaluationUser(200, 'ldap_1', '2025-01-01 00:00:00', '2024-01-01 00:00:00', 'ldap-user', 'ilias-user')]
        );
        $ldap = new ilUserCleanerTestLDAPLookup(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rule set ID 9 requires an available LDAP configuration');
        try {
            (new ilUserCleanerEvaluator($data, $ldap))->evaluate();
        } finally {
            self::assertSame(0, $ldap->lookupCount);
        }
    }

    private function rule(
        int $id,
        string $parameter,
        string $symbol = '=',
        int $value = 1,
        ilUserCleanerRuleSource $source = ilUserCleanerRuleSource::LOCAL_DATABASE,
        ?string $source_config_id = null
    ): ilUserCleanerRule {
        return new ilUserCleanerRule(
            $id,
            $id,
            $parameter,
            $symbol,
            $value,
            $source,
            !in_array($parameter, ['account_has_logged_in', 'account_never_logged_in', 'external_account_missing_ldap'], true),
            $source === ilUserCleanerRuleSource::LDAP,
            $source_config_id
        );
    }
}

final class ilUserCleanerTestEvaluationData implements ilUserCleanerEvaluationData
{
    public function __construct(
        private readonly array $sets,
        private readonly array $rules,
        private readonly array $authModes,
        private readonly array $roleUsers,
        private readonly array $users,
        private readonly int $actorId = -1,
        private readonly array $administrators = [],
        private readonly array $exclusions = []
    ) {
    }

    public function getRuleSets(): array { return $this->sets; }
    public function getRules(ilUserCleanerRuleSet $rule_set): array { return $this->rules[$rule_set->id] ?? []; }
    public function getAuthMode(int $auth_id): ?string { return $this->authModes[$auth_id] ?? null; }
    public function getAssignedUserIds(int $role_id): array { return $this->roleUsers[$role_id] ?? []; }
    public function isAdministrator(int $user_id): bool { return in_array($user_id, $this->administrators, true); }
    public function isExcluded(int $user_id): bool { return in_array($user_id, $this->exclusions, true); }
    public function getActorId(): int { return $this->actorId; }
    public function getUser(int $user_id): ?ilUserCleanerEvaluationUser { return $this->users[$user_id] ?? null; }
}

final class ilUserCleanerTestLDAPLookup implements ilUserCleanerLDAPAccountLookup
{
    public int $lookupCount = 0;

    public function __construct(private readonly bool $unavailable = false)
    {
    }

    public function assertConfigurationAvailable(string $configuration_id): void
    {
        if ($this->unavailable) {
            throw new RuntimeException('LDAP server unavailable.');
        }
    }

    public function accountExists(string $configuration_id, string $account): bool
    {
        ++$this->lookupCount;
        return true;
    }
}
