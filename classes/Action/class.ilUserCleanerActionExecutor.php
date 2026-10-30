<?php
declare(strict_types=1);

require_once __DIR__ . '/../Evaluation/class.ilUserCleanerDecision.php';

final class ilUserCleanerActionExecutor
{
    private ilUserCleanerExclusionRepository $exclusions;
    private ilLogger $logger;
    private int $actorId;
    private ilUserCleanerActionProtocol $protocol;
    private Closure $loadUser;
    private Closure $invalidateSessions;
    private ilRbacReview $rbacReview;

    public function __construct(
        ?ilUserCleanerExclusionRepository $exclusions = null,
        ?ilLogger $logger = null,
        ?int $actor_id = null,
        ?ilUserCleanerActionProtocol $protocol = null,
        ?ilRbacReview $rbac_review = null,
        ?Closure $load_user = null,
        ?Closure $invalidate_sessions = null
    ) {
        global $DIC;

        $this->exclusions = $exclusions ?? new ilUserCleanerExclusionRepository();
        $this->logger = $logger ?? $DIC->logger()->user();
        $this->actorId = $actor_id ?? $DIC->user()->getId();
        $this->protocol = $protocol ?? new ilUserCleanerProtocolRepository();
        $this->rbacReview = $rbac_review ?? $DIC->rbac()->review();
        $this->loadUser = $load_user ?? static fn(int $id) => ilObjectFactory::getInstanceByObjId($id, false);
        $this->invalidateSessions = $invalidate_sessions ?? static fn(int $id): bool => ilSession::_destroyByUserId($id);
    }

    /** @param array<int, int[]> $matched_rule_set_ids_by_user_id */
    public function execute(
        array $matched_rule_set_ids_by_user_id,
        ilUserCleanerCleanupAction $action,
        ?Closure $heartbeat = null
    ): ilUserCleanerActionResult
    {
        $changed = 0;
        $unchanged = 0;
        $failed = 0;

        foreach ($matched_rule_set_ids_by_user_id as $user_id => $matched_rule_set_ids) {
            $heartbeat?->__invoke();
            if (ilUserCleanerDecision::isProtected(
                $user_id,
                $this->actorId,
                $this->rbacReview->isAssigned($user_id, SYSTEM_ROLE_ID),
                $this->exclusions->containsUser($user_id)
            )) {
                ++$unchanged;
                $this->logger->debug(sprintf(
                    'UserCleaner did not modify protected user ID %d.',
                    $user_id
                ));
                continue;
            }

            $protocol_id = null;
            $action_completed = false;
            try {
                if ($action === ilUserCleanerCleanupAction::DELETE && $this->protocol->hasDeleteAttempt($user_id)) {
                    ++$failed;
                    $this->logger->warning(sprintf(
                        'UserCleaner skipped user ID %d: a deletion protocol already exists. Review its outcome before any further action.',
                        $user_id
                    ));
                    continue;
                }
                $user = ($this->loadUser)($user_id);
                if (!$user instanceof ilObjUser) {
                    ++$unchanged;
                    $this->logger->warning(sprintf(
                        'UserCleaner could not load matched user ID %d; no action was applied.',
                        $user_id
                    ));
                    continue;
                }

                $protocol_id = $this->protocol->start($user, $action, $matched_rule_set_ids);

                if ($action === ilUserCleanerCleanupAction::DEACTIVATE) {
                    if (!$user->getActive()) {
                        $this->protocol->finish($protocol_id, 'unchanged');
                        ++$unchanged;
                        $this->logger->debug(sprintf(
                            'UserCleaner did not deactivate user ID %d because the account is already inactive.',
                            $user_id
                        ));
                        continue;
                    }
                    $user->setActive(false, $this->actorId);
                    if (!$user->update()) {
                        throw new RuntimeException('User update returned false.');
                    }
                } else {
                    if ($user->getActive()) {
                        $user->setActive(false, $this->actorId);
                        if (!$user->update()) {
                            throw new RuntimeException('User deactivation returned false; deletion was not started.');
                        }
                    }
                    if (!(($this->invalidateSessions)($user_id))) {
                        throw new RuntimeException('Session invalidation failed; deletion was not started.');
                    }
                    $this->protocol->finish($protocol_id, 'deleting');
                    $heartbeat?->__invoke();
                    if (!$user->delete()) {
                        throw new RuntimeException('User deletion returned false; the account may be partially deleted.');
                    }
                }

                $action_completed = true;
                ++$changed;
                $this->protocol->finish($protocol_id, 'success');
                $this->logger->info(sprintf(
                    'UserCleaner applied action "%s" to user ID %d.',
                    $action->value,
                    $user_id
                ));
            } catch (Throwable $exception) {
                ++$failed;
                if ($protocol_id !== null && !$action_completed) {
                    try {
                        $this->protocol->finish($protocol_id, 'failed', $exception->getMessage());
                    } catch (Throwable $protocol_exception) {
                        $this->logger->error(sprintf(
                            'UserCleaner could not finish protocol ID %d: %s',
                            $protocol_id,
                            $protocol_exception->getMessage()
                        ));
                    }
                }
                if ($action_completed) {
                    $this->logger->error(sprintf(
                        'UserCleaner completed action "%s" for user ID %d, but recording success failed: %s. Protocol ID %d requires review.',
                        $action->value,
                        $user_id,
                        $exception->getMessage(),
                        $protocol_id
                    ));
                    continue;
                }
                $this->logger->error(sprintf(
                    'UserCleaner failed to apply action "%s" to user ID %d: %s',
                    $action->value,
                    $user_id,
                    $exception->getMessage()
                ));
            }
        }

        return new ilUserCleanerActionResult($changed, $unchanged, $failed);
    }
}
