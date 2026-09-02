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

use ILIAS\Cron\Schedule\CronJobScheduleType;

require_once __DIR__ . '/Export/class.ilUserCleanerProtocolExportRepository.php';

class ilUserCleanerJob extends ilCronJob
{
    public function getId(): string
    {
        return "ucc";
    }

    public function getTitle(): string
    {
        return "User Cleaner";
    }

    public function getDescription(): string
    {
        return "Should clean up old unused users";
    }

    public function hasAutoActivation(): bool
    {
        return false;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function getCustomSettingsGUIClass(): string
    {
        return ilUserCleanerConfigGUI::class;
    }

    public function getDefaultScheduleType(): CronJobScheduleType
    {
        return CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS;
    }

    public function getDefaultScheduleValue(): ?int
    {
        return 1;
    }

    public function hasCustomSettings(): bool
    {
        return true;
    }

    public function run(): ilCronJobResult
    {
        global $DIC;

        $result = new ilCronJobResult();
        $logger = $DIC->logger()->user();
        $started_at = microtime(true);
        $logger->info('UserCleaner cron run started.');

        try {
            $settings = new ilUserCleanerSettingsRepository();
            $retention = $settings->getProtocolRetention();
            if ($retention !== null) {
                $cutoff = $retention->getCutoff(new DateTimeImmutable());
                (new ilUserCleanerProtocolRepository())->deleteOlderThan(
                    $cutoff
                );
                (new ilUserCleanerProtocolExportRepository())->deleteOlderThan(
                    $cutoff
                );
                $logger->info(sprintf(
                    'UserCleaner protocol retention cleanup completed for records before %s.',
                    $cutoff->format(DateTimeInterface::ATOM)
                ));
            }
            $evaluation = (new ilUserCleanerEvaluator())->evaluate();
            $action_result = null;
            if ($settings->isDryRun()) {
                $this->recordDryRunCandidates($evaluation);
                $message = sprintf(
                    'Dry run: %d user(s) matched %d enabled rule set(s). No accounts were changed.',
                    $evaluation->getMatchedUserCount(),
                    $evaluation->enabledRuleSetCount
                );
                $this->logDryRunDiagnostics($evaluation);
            } else {
                $action = $settings->getCleanupAction();
                $action_result = (new ilUserCleanerActionExecutor())->execute(
                    $evaluation->matchedRuleSetIdsByUserId,
                    $action
                );
                $message = sprintf(
                    'Action "%s": %d user(s) matched; %d changed, %d unchanged, %d failed.',
                    $action->value,
                    $evaluation->getMatchedUserCount(),
                    $action_result->changed,
                    $action_result->unchanged,
                    $action_result->failed
                );
            }
            if ($evaluation->unsupportedRuleSetIds !== []) {
                $message .= sprintf(
                    ' %d rule set(s) with unsupported or unavailable external checks were skipped.',
                    count($evaluation->unsupportedRuleSetIds)
                );
            }

            $result->setStatus(
                $action_result !== null && $action_result->failed > 0
                    ? ilCronJobResult::STATUS_FAIL
                    : ilCronJobResult::STATUS_OK
            );
            $result->setMessage($message);
            $logger->info(sprintf(
                'UserCleaner cron run completed in %.3f seconds: %s',
                microtime(true) - $started_at,
                $message
            ));
        } catch (Throwable $e) {
            $result->setStatus(ilCronJobResult::STATUS_FAIL);
            $result->setMessage($e->getMessage());
            $logger->error(sprintf(
                'UserCleaner cron run failed after %.3f seconds: %s',
                microtime(true) - $started_at,
                $e->getMessage()
            ));
            $logger->debug($e->getTraceAsString());
        }

        return $result;
    }

    private function recordDryRunCandidates(ilUserCleanerEvaluationResult $evaluation): void
    {
        $protocol = new ilUserCleanerProtocolRepository();
        foreach ($evaluation->matchedRuleSetIdsByUserId as $user_id => $rule_set_ids) {
            $user = ilObjectFactory::getInstanceByObjId($user_id, false);
            if (!$user instanceof ilObjUser) {
                throw new RuntimeException(sprintf('Could not load dry-run candidate user ID %d.', $user_id));
            }
            $protocol_id = $protocol->start($user, 'dry_run', $rule_set_ids);
            $protocol->finish($protocol_id, 'dry_run');
        }
    }

    private function logDryRunDiagnostics(ilUserCleanerEvaluationResult $evaluation): void
    {
        global $DIC;
        foreach ($evaluation->ruleDiagnosticsByUserId as $user_id => $sets) {
            foreach ($sets as $rule_set_id => $rules) {
                $DIC->logger()->user()->debug(sprintf(
                    'UserCleaner dry-run user ID %d, rule set ID %d: %s',
                    $user_id,
                    $rule_set_id,
                    implode(', ', array_map(
                        static fn(int $rule_id, bool $matched): string => sprintf(
                            'rule %d=%s',
                            $rule_id,
                            $matched ? 'matched' : 'failed'
                        ),
                        array_keys($rules),
                        array_values($rules)
                    ))
                ));
            }
        }
    }
}
