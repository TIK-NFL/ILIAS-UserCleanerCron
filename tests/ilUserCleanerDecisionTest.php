<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ilUserCleanerDecisionTest extends TestCase
{
    // A user must satisfy every rule in one set, but matching any one set is sufficient.
    public function testRulesInsideSetUseAndAndSetsUseOr(): void
    {
        self::assertTrue(ilUserCleanerDecision::matchesRuleSet([true, true]));
        self::assertFalse(ilUserCleanerDecision::matchesRuleSet([true, false]));
        self::assertFalse(ilUserCleanerDecision::matchesRuleSet([]));
        self::assertTrue(ilUserCleanerDecision::matchesAnyRuleSet([false, true]));
        self::assertFalse(ilUserCleanerDecision::matchesAnyRuleSet([false, false]));
    }

    // Three OR-linked sets select local guest users, users inactive for over 600 days,
    // or LDAP users that are both missing from LDAP and inactive for over 300 days.
    public function testRealisticThreeRuleSetCleanupScenario(): void
    {
        $users = [
            [
                'login' => 'local_guest',
                'auth_mode' => 'local',
                'guest_role' => true,
                'last_login_days' => 20,
                'exists_in_ldap' => true,
                'protected' => false,
            ],
            [
                'login' => 'very_old_local',
                'auth_mode' => 'local',
                'guest_role' => false,
                'last_login_days' => 700,
                'exists_in_ldap' => true,
                'protected' => false,
            ],
            [
                'login' => 'old_missing_ldap',
                'auth_mode' => 'ldap_1',
                'guest_role' => false,
                'last_login_days' => 400,
                'exists_in_ldap' => false,
                'protected' => false,
            ],
            [
                'login' => 'recent_missing_ldap',
                'auth_mode' => 'ldap_1',
                'guest_role' => false,
                'last_login_days' => 200,
                'exists_in_ldap' => false,
                'protected' => false,
            ],
            [
                'login' => 'old_existing_ldap',
                'auth_mode' => 'ldap_1',
                'guest_role' => false,
                'last_login_days' => 400,
                'exists_in_ldap' => true,
                'protected' => false,
            ],
            [
                'login' => 'recent_local',
                'auth_mode' => 'local',
                'guest_role' => false,
                'last_login_days' => 100,
                'exists_in_ldap' => true,
                'protected' => false,
            ],
            [
                'login' => 'protected_local_guest',
                'auth_mode' => 'local',
                'guest_role' => true,
                'last_login_days' => 700,
                'exists_in_ldap' => true,
                'protected' => true,
            ],
        ];

        $cleanup_candidates = [];
        foreach ($users as $user) {
            if ($user['protected']) {
                continue;
            }

            $local_guest_set = ilUserCleanerDecision::matchesRuleSet([
                $user['guest_role'],
                ilUserCleanerDecision::matchesAuthMode($user['auth_mode'], 'local'),
            ]);
            $older_than_600_days_set = ilUserCleanerDecision::matchesRuleSet([
                ilUserCleanerDecision::compare($user['last_login_days'], '>', 600),
            ]);
            $missing_ldap_and_older_than_300_days_set = ilUserCleanerDecision::matchesRuleSet([
                ilUserCleanerDecision::matchesAuthMode($user['auth_mode'], 'ldap'),
                !$user['exists_in_ldap'],
                ilUserCleanerDecision::compare($user['last_login_days'], '>', 300),
            ]);

            if (ilUserCleanerDecision::matchesAnyRuleSet([
                $local_guest_set,
                $older_than_600_days_set,
                $missing_ldap_and_older_than_300_days_set,
            ])) {
                $cleanup_candidates[] = $user['login'];
            }
        }

        self::assertSame(
            ['local_guest', 'very_old_local', 'old_missing_ldap'],
            $cleanup_candidates
        );
    }

    // Strict greater-than rules do not match on the configured day itself, only one day later.
    public function testCleanupThresholdsUseStrictDayBoundaries(): void
    {
        self::assertFalse(ilUserCleanerDecision::compare(300, '>', 300));
        self::assertTrue(ilUserCleanerDecision::compare(301, '>', 300));
        self::assertFalse(ilUserCleanerDecision::compare(600, '>', 600));
        self::assertTrue(ilUserCleanerDecision::compare(601, '>', 600));
    }

    // Day rules use exact elapsed days, while month rules use completed 30-day periods.
    public function testDayAndMonthRulesUseDifferentUnits(): void
    {
        $now = new DateTimeImmutable('2026-08-21 00:00:00');
        $last_login = '2025-08-21 00:00:00';

        $days = ilUserCleanerDecision::ageInDays($last_login, $now);
        $months = ilUserCleanerDecision::ageInMonths($last_login, $now);

        self::assertSame(365, $days);
        self::assertSame(12, $months);
        self::assertTrue(ilUserCleanerDecision::compare($days, '>', 360));
        self::assertFalse(ilUserCleanerDecision::compare($months, '>', 12));
    }

    // A month threshold matches only after another complete 30-day period has elapsed.
    public function testMonthRuleBoundaryUsesCompletedThirtyDayPeriods(): void
    {
        $now = new DateTimeImmutable('2026-08-21 00:00:00');

        self::assertSame(
            11,
            ilUserCleanerDecision::ageInMonths($now->modify('-359 days')->format('Y-m-d H:i:s'), $now)
        );
        self::assertSame(
            12,
            ilUserCleanerDecision::ageInMonths($now->modify('-360 days')->format('Y-m-d H:i:s'), $now)
        );
        self::assertSame(
            13,
            ilUserCleanerDecision::ageInMonths($now->modify('-390 days')->format('Y-m-d H:i:s'), $now)
        );
    }

    // A missing login no longer matches age rules and must be selected by an explicit state rule.
    public function testNeverLoggedInAccountRequiresExplicitStateRule(): void
    {
        $now = new DateTimeImmutable('2026-08-21 00:00:00');
        $days = ilUserCleanerDecision::ageInDays(null, $now);
        $months = ilUserCleanerDecision::ageInMonths(null, $now);

        self::assertSame(0, $days);
        self::assertFalse(ilUserCleanerDecision::compare($days, '>', 600));
        self::assertFalse(ilUserCleanerDecision::compare($months, '>', 24));
        self::assertFalse(ilUserCleanerDecision::hasLoggedIn(null));
        self::assertFalse(ilUserCleanerDecision::hasLoggedIn(''));
        self::assertTrue(ilUserCleanerDecision::hasLoggedIn('2025-01-01 00:00:00'));
    }

    // A minimum account-age rule can safely delay cleanup of accounts that never logged in.
    public function testMinimumAccountAgeAndNeverLoggedInRulesWorkTogether(): void
    {
        $now = new DateTimeImmutable('2026-08-21 00:00:00');
        $new_account_age = ilUserCleanerDecision::ageInDays('2026-08-01 00:00:00', $now);
        $old_account_age = ilUserCleanerDecision::ageInDays('2025-06-01 00:00:00', $now);

        $new_never_logged_in_account = ilUserCleanerDecision::matchesRuleSet([
            !ilUserCleanerDecision::hasLoggedIn(null),
            ilUserCleanerDecision::compare($new_account_age, '>', 300),
        ]);
        $old_never_logged_in_account = ilUserCleanerDecision::matchesRuleSet([
            !ilUserCleanerDecision::hasLoggedIn(null),
            ilUserCleanerDecision::compare($old_account_age, '>', 300),
        ]);

        self::assertFalse($new_never_logged_in_account);
        self::assertTrue($old_never_logged_in_account);
    }

    // Four OR-linked sets target old staff, abandoned guests, departed LDAP students,
    // and stale temporary accounts while respecting role, authentication, and AND rules.
    public function testFourRuleSetsUsingAccountAgeAndLoginStateRules(): void
    {
        $users = [
            [
                'login' => 'old_staff',
                'roles' => ['staff'],
                'auth_mode' => 'local',
                'account_age_days' => 800,
                'last_login_days' => 250,
                'exists_in_ldap' => true,
            ],
            [
                'login' => 'active_staff',
                'roles' => ['staff'],
                'auth_mode' => 'local',
                'account_age_days' => 800,
                'last_login_days' => 20,
                'exists_in_ldap' => true,
            ],
            [
                'login' => 'abandoned_guest',
                'roles' => ['guest'],
                'auth_mode' => 'local',
                'account_age_days' => 220,
                'last_login_days' => null,
                'exists_in_ldap' => true,
            ],
            [
                'login' => 'new_guest',
                'roles' => ['guest'],
                'auth_mode' => 'local',
                'account_age_days' => 60,
                'last_login_days' => null,
                'exists_in_ldap' => true,
            ],
            [
                'login' => 'departed_student',
                'roles' => ['student'],
                'auth_mode' => 'ldap_1',
                'account_age_days' => 500,
                'last_login_days' => 120,
                'exists_in_ldap' => false,
            ],
            [
                'login' => 'existing_student',
                'roles' => ['student'],
                'auth_mode' => 'ldap_1',
                'account_age_days' => 500,
                'last_login_days' => 120,
                'exists_in_ldap' => true,
            ],
            [
                'login' => 'stale_temporary',
                'roles' => ['temporary'],
                'auth_mode' => 'local',
                'account_age_days' => 500,
                'last_login_days' => 45,
                'exists_in_ldap' => true,
            ],
            [
                'login' => 'young_temporary',
                'roles' => ['temporary'],
                'auth_mode' => 'local',
                'account_age_days' => 100,
                'last_login_days' => 45,
                'exists_in_ldap' => true,
            ],
            [
                'login' => 'old_outsider',
                'roles' => ['other'],
                'auth_mode' => 'local',
                'account_age_days' => 900,
                'last_login_days' => 700,
                'exists_in_ldap' => false,
            ],
            [
                'login' => 'staff_and_temporary',
                'roles' => ['staff', 'temporary'],
                'auth_mode' => 'local',
                'account_age_days' => 900,
                'last_login_days' => 300,
                'exists_in_ldap' => true,
            ],
        ];

        $candidate_logins = [];
        foreach ($users as $user) {
            $has_logged_in = $user['last_login_days'] !== null;

            // Set 1: local staff who logged in before, but not within the last 200 days.
            $old_staff = ilUserCleanerDecision::matchesRuleSet([
                in_array('staff', $user['roles'], true),
                ilUserCleanerDecision::matchesAuthMode($user['auth_mode'], 'local'),
                $has_logged_in,
                ilUserCleanerDecision::compare($user['last_login_days'] ?? 0, '>', 200),
            ]);

            // Set 2: local guests that never logged in and are at least six 30-day months old.
            $abandoned_guest = ilUserCleanerDecision::matchesRuleSet([
                in_array('guest', $user['roles'], true),
                ilUserCleanerDecision::matchesAuthMode($user['auth_mode'], 'local'),
                !$has_logged_in,
                ilUserCleanerDecision::compare(intdiv($user['account_age_days'], 30), '>=', 6),
            ]);

            // Set 3: LDAP students missing from LDAP and inactive for more than 90 days.
            $departed_student = ilUserCleanerDecision::matchesRuleSet([
                in_array('student', $user['roles'], true),
                ilUserCleanerDecision::matchesAuthMode($user['auth_mode'], 'ldap'),
                $has_logged_in,
                !$user['exists_in_ldap'],
                ilUserCleanerDecision::compare($user['last_login_days'] ?? 0, '>', 90),
            ]);

            // Set 4: local temporary accounts older than a year and inactive for over 30 days.
            $stale_temporary = ilUserCleanerDecision::matchesRuleSet([
                in_array('temporary', $user['roles'], true),
                ilUserCleanerDecision::matchesAuthMode($user['auth_mode'], 'local'),
                ilUserCleanerDecision::compare($user['account_age_days'], '>', 365),
                $has_logged_in,
                ilUserCleanerDecision::compare($user['last_login_days'] ?? 0, '>', 30),
            ]);

            if (ilUserCleanerDecision::matchesAnyRuleSet([
                $old_staff,
                $abandoned_guest,
                $departed_student,
                $stale_temporary,
            ])) {
                $candidate_logins[$user['login']] = $user['login'];
            }
        }

        self::assertSame(
            ['old_staff', 'abandoned_guest', 'departed_student', 'stale_temporary', 'staff_and_temporary'],
            array_values($candidate_logins)
        );
    }

    // Old accounts cannot cross-match a rule set configured for another authentication mode.
    public function testAuthenticationModeKeepsLocalAndLdapRuleSetsIsolated(): void
    {
        $local_user_in_ldap_set = ilUserCleanerDecision::matchesRuleSet([
            ilUserCleanerDecision::matchesAuthMode('local', 'ldap'),
            ilUserCleanerDecision::compare(700, '>', 300),
        ]);
        $ldap_user_in_local_set = ilUserCleanerDecision::matchesRuleSet([
            ilUserCleanerDecision::matchesAuthMode('ldap_1', 'local'),
            ilUserCleanerDecision::compare(700, '>', 300),
        ]);

        self::assertFalse($local_user_in_ldap_set);
        self::assertFalse($ldap_user_in_local_set);
    }

    // A missing LDAP account alone is insufficient when the same set also requires an old login.
    public function testLdapAndRuleRejectsPartialMatches(): void
    {
        $missing_but_recent = ilUserCleanerDecision::matchesRuleSet([
            true,
            ilUserCleanerDecision::compare(200, '>', 300),
        ]);
        $old_but_existing = ilUserCleanerDecision::matchesRuleSet([
            false,
            ilUserCleanerDecision::compare(400, '>', 300),
        ]);
        $missing_and_old = ilUserCleanerDecision::matchesRuleSet([
            true,
            ilUserCleanerDecision::compare(400, '>', 300),
        ]);

        self::assertFalse($missing_but_recent);
        self::assertFalse($old_but_existing);
        self::assertTrue($missing_and_old);
    }

    // LDAP checks prefer the external account and fall back to the ILIAS login when it is blank.
    public function testExternalAccountIdentifierFallsBackToLogin(): void
    {
        self::assertSame(
            'directory-account',
            ilUserCleanerDecision::externalAccountIdentifier(' directory-account ', 'ilias-login')
        );
        self::assertSame(
            'ilias-login',
            ilUserCleanerDecision::externalAccountIdentifier('', 'ilias-login')
        );
        self::assertSame(
            'ilias-login',
            ilUserCleanerDecision::externalAccountIdentifier('   ', 'ilias-login')
        );
    }

    // Permanent protection takes precedence even when every configured rule set matches.
    public function testProtectionOverridesMatchingRuleSets(): void
    {
        $actor_id = 99;
        $otherwise_matching_sets = [true, true, true];
        $protected_users = [
            [ANONYMOUS_USER_ID, false, false],
            [SYSTEM_USER_ID, false, false],
            [$actor_id, false, false],
            [100, true, false],
            [101, false, true],
        ];

        foreach ($protected_users as [$user_id, $is_administrator, $is_excluded]) {
            $is_candidate = !ilUserCleanerDecision::isProtected(
                $user_id,
                $actor_id,
                $is_administrator,
                $is_excluded
            ) && ilUserCleanerDecision::matchesAnyRuleSet($otherwise_matching_sets);

            self::assertFalse($is_candidate, sprintf('Protected user ID %d became a candidate.', $user_id));
        }
    }

    // Matching several OR-linked sets still creates one cleanup candidate for the user.
    public function testUserMatchingMultipleSetsIsSelectedOnlyOnce(): void
    {
        $users_with_rule_set_results = [
            200 => [true, true, false],
            201 => [false, false, false],
        ];
        $candidate_user_ids = [];

        foreach ($users_with_rule_set_results as $user_id => $rule_set_results) {
            if (ilUserCleanerDecision::matchesAnyRuleSet($rule_set_results)) {
                $candidate_user_ids[$user_id] = $user_id;
            }
        }

        self::assertSame([200], array_values($candidate_user_ids));
    }

    // Authentication must match exactly, while configured LDAP also accepts numbered LDAP modes.
    #[DataProvider('authModes')]
    public function testAuthenticationModes(string $user, string $configured, bool $expected): void
    {
        self::assertSame($expected, ilUserCleanerDecision::matchesAuthMode($user, $configured));
    }

    public static function authModes(): array
    {
        return [
            ['local', 'local', true],
            ['ldap', 'ldap', true],
            ['ldap_1', 'ldap', true],
            ['ldap_2', 'ldap', true],
            ['ldap_1', 'local', false],
        ];
    }

    // Login age handles exact day boundaries and treats missing or future logins as zero days old.
    public function testDayBoundariesAndNeverLoggedIn(): void
    {
        $now = new DateTimeImmutable('2026-08-21 00:00:00');
        self::assertSame(100, ilUserCleanerDecision::ageInDays('2026-05-13 00:00:00', $now));
        self::assertSame(0, ilUserCleanerDecision::ageInDays('2026-08-22 00:00:00', $now));
        self::assertSame(0, ilUserCleanerDecision::ageInDays(null, $now));
        self::assertTrue(ilUserCleanerDecision::compare(500, '>=', 500));
        self::assertFalse(ilUserCleanerDecision::compare(499, '>=', 500));
    }

    // Built-in system accounts, the executing user, administrators, and exclusions stay protected.
    public function testPermanentProtections(): void
    {
        self::assertTrue(ilUserCleanerDecision::isProtected(ANONYMOUS_USER_ID, 99, false, false));
        self::assertTrue(ilUserCleanerDecision::isProtected(SYSTEM_USER_ID, 99, false, false));
        self::assertTrue(ilUserCleanerDecision::isProtected(99, 99, false, false));
        self::assertTrue(ilUserCleanerDecision::isProtected(100, 99, true, false));
        self::assertTrue(ilUserCleanerDecision::isProtected(100, 99, false, true));
        self::assertFalse(ilUserCleanerDecision::isProtected(100, 99, false, false));
    }

    // Protocol-retention cutoffs subtract the configured number of calendar days or months.
    public function testRetentionCutoffs(): void
    {
        $now = new DateTimeImmutable('2026-08-21 12:00:00');
        self::assertSame(
            '2026-08-11 12:00:00',
            (new ilUserCleanerRetention(10, ilUserCleanerRetentionUnit::DAYS))
                ->getCutoff($now)->format('Y-m-d H:i:s')
        );
        self::assertSame(
            '2026-06-21 12:00:00',
            (new ilUserCleanerRetention(2, ilUserCleanerRetentionUnit::MONTHS))
                ->getCutoff($now)->format('Y-m-d H:i:s')
        );
    }
}
