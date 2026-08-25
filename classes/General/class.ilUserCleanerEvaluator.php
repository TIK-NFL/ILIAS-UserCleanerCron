<?php
declare(strict_types=1);

require_once __DIR__ . '/class.ilUserCleanerDecision.php';

final class ilUserCleanerEvaluator
{
    private ilUserCleanerRuleSetRepository $ruleSets;
    private ilUserCleanerRuleSetRuleRepository $memberships;
    private ilUserCleanerRuleRepository $rules;
    private ilUserCleanerAuthModeRepository $authModes;
    private ilUserCleanerExclusionRepository $exclusions;
    private ilRbacReview $rbacReview;
    private ilUserCleanerLDAPAccountChecker $ldapAccounts;

    public function __construct()
    {
        global $DIC;

        $this->ruleSets = new ilUserCleanerRuleSetRepository();
        $this->memberships = new ilUserCleanerRuleSetRuleRepository();
        $this->rules = new ilUserCleanerRuleRepository();
        $this->authModes = new ilUserCleanerAuthModeRepository();
        $this->exclusions = new ilUserCleanerExclusionRepository();
        $this->rbacReview = $DIC->rbac()->review();
        $this->ldapAccounts = new ilUserCleanerLDAPAccountChecker();
    }

    public function evaluate(?DateTimeImmutable $now = null): ilUserCleanerEvaluationResult
    {
        $now ??= new DateTimeImmutable();
        $matches = [];
        $enabled_rule_set_count = 0;
        $unsupported_rule_set_ids = [];
        $diagnostics = [];

        foreach ($this->ruleSets->getAll() as $rule_set) {
            if (!$rule_set->enabled) {
                continue;
            }
            ++$enabled_rule_set_count;

            $rules = $this->getRules($rule_set);
            if ($rules === []) {
                continue;
            }
            if (!$this->supportsAll($rules)) {
                $unsupported_rule_set_ids[] = $rule_set->id;
                continue;
            }

            $auth_mode = $this->authModes->getModeById($rule_set->authId);
            if ($auth_mode === null) {
                $unsupported_rule_set_ids[] = $rule_set->id;
                continue;
            }

            $rule_set_matches = [];
            try {
                foreach ($this->rbacReview->assignedUsers($rule_set->roleId) as $user_id) {
                    if (ilUserCleanerDecision::isProtected(
                        $user_id,
                        -1,
                        $this->rbacReview->isAssigned($user_id, SYSTEM_ROLE_ID),
                        $this->exclusions->containsUser($user_id)
                    )) {
                        continue;
                    }

                    $user = new ilObjUser($user_id);
                    if ($user->getId() <= 0 || !ilUserCleanerDecision::matchesAuthMode(
                        (string) $user->getAuthMode(),
                        $auth_mode
                    )) {
                        continue;
                    }
                    $rule_results = $this->evaluateRules($user, $rules, $now);
                    $diagnostics[$user_id][$rule_set->id] = $rule_results;
                    if (ilUserCleanerDecision::matchesRuleSet(array_values($rule_results))) {
                        $rule_set_matches[] = $user_id;
                    }
                }
            } catch (RuntimeException) {
                $unsupported_rule_set_ids[] = $rule_set->id;
                continue;
            }
            foreach ($rule_set_matches as $user_id) {
                $matches[$user_id][] = $rule_set->id;
            }
        }

        ksort($matches);
        return new ilUserCleanerEvaluationResult(
            $matches,
            $enabled_rule_set_count,
            array_values(array_unique($unsupported_rule_set_ids)),
            $diagnostics
        );
    }

    /** @return ilUserCleanerRule[] */
    private function getRules(ilUserCleanerRuleSet $rule_set): array
    {
        $rules = [];
        foreach ($this->memberships->getByRuleSetId($rule_set->id) as $membership) {
            $rule = $this->rules->getById($membership->ruleId);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
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
    private function evaluateRules(ilObjUser $user, array $rules, DateTimeImmutable $now): array
    {
        $results = [];
        foreach ($rules as $rule) {
            $results[$rule->id] = $this->matches($user, $rule, $now);
        }
        return $results;
    }

    private function matches(ilObjUser $user, ilUserCleanerRule $rule, DateTimeImmutable $now): bool
    {
        if ($rule->parameter === 'external_account_missing_ldap') {
            $account = trim($user->getExternalAccount());
            if ($account === '') {
                $account = $user->getLogin();
            }

            return !$this->ldapAccounts->accountExists((string) $rule->sourceConfigId, $account);
        }
        $days = ilUserCleanerDecision::ageInDays($user->getLastLogin(), $now);
        $age = $rule->parameter === 'last_login_months' ? intdiv($days, 30) : $days;
        return ilUserCleanerDecision::compare($age, $rule->symbol, $rule->value);
    }
}
