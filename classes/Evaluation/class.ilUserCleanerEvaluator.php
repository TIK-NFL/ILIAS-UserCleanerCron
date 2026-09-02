<?php
declare(strict_types=1);

require_once __DIR__ . '/class.ilUserCleanerDecision.php';

final class ilUserCleanerEvaluator
{
    private ilUserCleanerEvaluationData $data;
    private ilUserCleanerLDAPAccountLookup $ldapAccounts;
    private ?ilLogger $logger;

    public function __construct(
        ?ilUserCleanerEvaluationData $data = null,
        ?ilUserCleanerLDAPAccountLookup $ldap_accounts = null,
        ?ilLogger $logger = null
    ) {
        if ($data === null) {
            global $DIC;
            $data = new ilUserCleanerEvaluationRepository();
            $logger ??= $DIC->logger()->user();
        }
        $this->data = $data;
        $this->ldapAccounts = $ldap_accounts ?? new ilUserCleanerLDAPAccountChecker();
        $this->logger = $logger;
    }

    public function evaluate(?DateTimeImmutable $now = null): ilUserCleanerEvaluationResult
    {
        $now ??= new DateTimeImmutable();
        $matches = [];
        $enabled_rule_set_count = 0;
        $unsupported_rule_set_ids = [];
        $diagnostics = [];

        foreach ($this->data->getRuleSets() as $rule_set) {
            if (!$rule_set->enabled) {
                continue;
            }
            ++$enabled_rule_set_count;

            $rules = $this->data->getRules($rule_set);
            if ($rules === []) {
                $this->logger?->debug(sprintf(
                    'UserCleaner skipped enabled rule set ID %d because it has no rules.',
                    $rule_set->id
                ));
                continue;
            }
            if (!$this->supportsAll($rules)) {
                $unsupported_rule_set_ids[] = $rule_set->id;
                $this->logger?->warning(sprintf(
                    'UserCleaner skipped rule set ID %d because it contains an unsupported rule type.',
                    $rule_set->id
                ));
                continue;
            }
            $this->assertExternalSourcesAvailable($rule_set, $rules);

            $auth_mode = $this->data->getAuthMode($rule_set->authId);
            if ($auth_mode === null) {
                $unsupported_rule_set_ids[] = $rule_set->id;
                $this->logger?->warning(sprintf(
                    'UserCleaner skipped rule set ID %d because authentication mode ID %d does not exist.',
                    $rule_set->id,
                    $rule_set->authId
                ));
                continue;
            }

            $rule_set_matches = [];
            foreach ($this->data->getAssignedUserIds($rule_set->roleId) as $user_id) {
                if (ilUserCleanerDecision::isProtected(
                    $user_id,
                    $this->data->getActorId(),
                    $this->data->isAdministrator($user_id),
                    $this->data->isExcluded($user_id)
                )) {
                    $this->logger?->info(sprintf(
                        'UserCleaner skipped user ID %d in rule set ID %d because the account is protected.',
                        $user_id,
                        $rule_set->id
                    ));
                    continue;
                }

                $user = $this->data->getUser($user_id);
                if ($user === null) {
                    $this->logger?->info(sprintf(
                        'UserCleaner skipped user ID %d in rule set ID %d because the user object could not be loaded.',
                        $user_id,
                        $rule_set->id
                    ));
                    continue;
                }
                if (!ilUserCleanerDecision::matchesAuthMode(
                    $user->authMode,
                    $auth_mode
                )) {
                    $this->logger?->info(sprintf(
                        'UserCleaner skipped user ID %d in rule set ID %d because auth mode "%s" does not match "%s".',
                        $user_id,
                        $rule_set->id,
                        $user->authMode,
                        $auth_mode
                    ));
                    continue;
                }
                $rule_results = $this->evaluateRules($user, $rules, $now);
                $diagnostics[$user_id][$rule_set->id] = $rule_results;
                if (ilUserCleanerDecision::matchesRuleSet(array_values($rule_results))) {
                    $rule_set_matches[] = $user_id;
                }
            }
            foreach ($rule_set_matches as $user_id) {
                $matches[$user_id][] = $rule_set->id;
            }
        }

        ksort($matches);
        $this->logger?->info(sprintf(
            'UserCleaner evaluated %d enabled rule set(s): %d matching user(s), %d skipped rule set(s).',
            $enabled_rule_set_count,
            count($matches),
            count(array_unique($unsupported_rule_set_ids))
        ));
        return new ilUserCleanerEvaluationResult(
            $matches,
            $enabled_rule_set_count,
            array_values(array_unique($unsupported_rule_set_ids)),
            $diagnostics
        );
    }

    /** @param ilUserCleanerRule[] $rules */
    private function assertExternalSourcesAvailable(ilUserCleanerRuleSet $rule_set, array $rules): void
    {
        foreach ($rules as $rule) {
            if ($rule->parameter !== 'external_account_missing_ldap') {
                continue;
            }

            try {
                $this->ldapAccounts->assertConfigurationAvailable((string) $rule->sourceConfigId);
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf(
                    'Rule set ID %d requires an available LDAP configuration: %s',
                    $rule_set->id,
                    $exception->getMessage()
                ), 0, $exception);
            }
        }
    }

    /** @param ilUserCleanerRule[] $rules */
    private function supportsAll(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (!in_array(
                $rule->parameter,
                [
                    'last_login_days',
                    'last_login_months',
                    'account_age_days',
                    'account_age_months',
                    'account_has_logged_in',
                    'account_never_logged_in',
                    'external_account_missing_ldap',
                ],
                true
            )) {
                return false;
            }
        }

        return true;
    }

    /** @param ilUserCleanerRule[] $rules */
    private function evaluateRules(ilUserCleanerEvaluationUser $user, array $rules, DateTimeImmutable $now): array
    {
        $results = [];
        foreach ($rules as $rule) {
            $results[$rule->id] = $this->matches($user, $rule, $now);
        }
        return $results;
    }

    private function matches(ilUserCleanerEvaluationUser $user, ilUserCleanerRule $rule, DateTimeImmutable $now): bool
    {
        if ($rule->parameter === 'account_has_logged_in') {
            $matched = ilUserCleanerDecision::hasLoggedIn($user->lastLogin);
            $this->logRuleResult($user, $rule, $matched, sprintf(
                'last_login=%s',
                $user->lastLogin ?? 'null'
            ));
            return $matched;
        }
        if ($rule->parameter === 'account_never_logged_in') {
            $matched = !ilUserCleanerDecision::hasLoggedIn($user->lastLogin);
            $this->logRuleResult($user, $rule, $matched, sprintf(
                'last_login=%s',
                $user->lastLogin ?? 'null'
            ));
            return $matched;
        }
        if ($rule->parameter === 'external_account_missing_ldap') {
            $account = ilUserCleanerDecision::externalAccountIdentifier(
                $user->externalAccount,
                $user->login
            );
            $exists = $this->ldapAccounts->accountExists((string) $rule->sourceConfigId, $account);
            $matched = !$exists;
            $this->logRuleResult($user, $rule, $matched, sprintf(
                'configuration=%s, account=%s, exists=%s',
                $rule->sourceConfigId ?? 'null',
                $account,
                $exists ? 'true' : 'false'
            ));

            return $matched;
        }
        $is_account_age = str_starts_with($rule->parameter, 'account_age_');
        $date = $is_account_age ? $user->createDate : $user->lastLogin;
        if (!$is_account_age && !ilUserCleanerDecision::hasLoggedIn($date)) {
            $this->logRuleResult($user, $rule, false, 'date=null');
            return false;
        }
        $age = str_ends_with($rule->parameter, '_months')
            ? ilUserCleanerDecision::ageInMonths($date, $now)
            : ilUserCleanerDecision::ageInDays($date, $now);
        $matched = ilUserCleanerDecision::compare($age, $rule->symbol, $rule->value);
        $this->logRuleResult($user, $rule, $matched, sprintf(
            'date=%s, age=%d, comparison=%s %d',
            $date ?? 'null',
            $age,
            $rule->symbol,
            $rule->value
        ));
        return $matched;
    }

    private function logRuleResult(
        ilUserCleanerEvaluationUser $user,
        ilUserCleanerRule $rule,
        bool $matched,
        string $details
    ): void {
        $this->logger?->info(sprintf(
            'UserCleaner evaluated user ID %d, rule ID %d (%s): %s [%s].',
            $user->id,
            $rule->id,
            $rule->parameter,
            $matched ? 'matched' : 'failed',
            $details
        ));
    }
}
