<?php
declare(strict_types=1);

interface ilUserCleanerActionProtocol
{
    public function hasDeleteAttempt(int $user_id): bool;

    /** @param int[] $matched_rule_set_ids */
    public function start(ilObjUser $user, ilUserCleanerCleanupAction|string $action, array $matched_rule_set_ids): int;

    public function finish(int $protocol_id, string $status, ?string $error_message = null): void;
}
