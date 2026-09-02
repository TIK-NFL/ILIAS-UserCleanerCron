<?php
declare(strict_types=1);

require_once __DIR__ . '/../Evaluation/class.ilUserCleanerDecision.php';

final class ilUserCleanerActionExecutor
{
    private ilUserCleanerExclusionRepository $exclusions;
    private ilLogger $logger;
    private int $actorId;
    private ilUserCleanerProtocolRepository $protocol;
    private ilRbacReview $rbacReview;

    public function __construct()
    {
        global $DIC;

        $this->exclusions = new ilUserCleanerExclusionRepository();
        $this->logger = $DIC->logger()->user();
        $this->actorId = $DIC->user()->getId();
        $this->protocol = new ilUserCleanerProtocolRepository();
        $this->rbacReview = $DIC->rbac()->review();
    }

    /** @param array<int, int[]> $matched_rule_set_ids_by_user_id */
    public function execute(
        array $matched_rule_set_ids_by_user_id,
        ilUserCleanerCleanupAction $action
    ): ilUserCleanerActionResult
    {
        $changed = 0;
        $unchanged = 0;
        $failed = 0;

        foreach ($matched_rule_set_ids_by_user_id as $user_id => $matched_rule_set_ids) {
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
            try {
                $user = ilObjectFactory::getInstanceByObjId($user_id, false);
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
                } elseif (!$user->delete()) {
                    throw new RuntimeException('User deletion returned false.');
                }

                $this->protocol->finish($protocol_id, 'success');
                ++$changed;
                $this->logger->info(sprintf(
                    'UserCleaner applied action "%s" to user ID %d.',
                    $action->value,
                    $user_id
                ));
            } catch (Throwable $exception) {
                ++$failed;
                if ($protocol_id !== null) {
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
