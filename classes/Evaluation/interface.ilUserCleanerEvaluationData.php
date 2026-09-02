<?php
declare(strict_types=1);

interface ilUserCleanerEvaluationData
{
    /** @return ilUserCleanerRuleSet[] */
    public function getRuleSets(): array;

    /** @return ilUserCleanerRule[] */
    public function getRules(ilUserCleanerRuleSet $rule_set): array;

    public function getAuthMode(int $auth_id): ?string;

    /** @return int[] */
    public function getAssignedUserIds(int $role_id): array;

    public function isAdministrator(int $user_id): bool;

    public function isExcluded(int $user_id): bool;

    public function getActorId(): int;

    public function getUser(int $user_id): ?ilUserCleanerEvaluationUser;
}
