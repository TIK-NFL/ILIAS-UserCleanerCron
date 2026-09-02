<?php
declare(strict_types=1);

final class ilUserCleanerEvaluationRepository implements ilUserCleanerEvaluationData
{
    private ilUserCleanerRuleSetRepository $ruleSets;
    private ilUserCleanerRuleSetRuleRepository $memberships;
    private ilUserCleanerRuleRepository $rules;
    private ilUserCleanerAuthModeRepository $authModes;
    private ilUserCleanerExclusionRepository $exclusions;
    private ilRbacReview $rbacReview;
    private ilDBInterface $database;
    private int $actorId;

    public function __construct()
    {
        global $DIC;

        $this->ruleSets = new ilUserCleanerRuleSetRepository();
        $this->memberships = new ilUserCleanerRuleSetRuleRepository();
        $this->rules = new ilUserCleanerRuleRepository();
        $this->authModes = new ilUserCleanerAuthModeRepository();
        $this->exclusions = new ilUserCleanerExclusionRepository();
        $this->rbacReview = $DIC->rbac()->review();
        $this->database = $DIC->database();
        $this->actorId = $DIC->user()->getId();
    }

    public function getRuleSets(): array
    {
        return $this->ruleSets->getAll();
    }

    public function getRules(ilUserCleanerRuleSet $rule_set): array
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

    public function getAuthMode(int $auth_id): ?string
    {
        return $this->authModes->getModeById($auth_id);
    }

    public function getAssignedUserIds(int $role_id): array
    {
        return array_map('intval', $this->rbacReview->assignedUsers($role_id));
    }

    public function isAdministrator(int $user_id): bool
    {
        return $this->rbacReview->isAssigned($user_id, SYSTEM_ROLE_ID);
    }

    public function isExcluded(int $user_id): bool
    {
        return $this->exclusions->containsUser($user_id);
    }

    public function getActorId(): int
    {
        return $this->actorId;
    }

    public function getUser(int $user_id): ?ilUserCleanerEvaluationUser
    {
        $user = ilObjectFactory::getInstanceByObjId($user_id, false);
        if (!$user instanceof ilObjUser) {
            return null;
        }

        $result = $this->database->queryF(
            'SELECT create_date FROM usr_data WHERE usr_id = %s',
            ['integer'],
            [$user_id]
        );
        $row = $this->database->fetchAssoc($result);
        if (!is_array($row) || !isset($row['create_date'])) {
            return null;
        }

        return ilUserCleanerEvaluationUser::fromUser($user, (string) $row['create_date']);
    }
}
