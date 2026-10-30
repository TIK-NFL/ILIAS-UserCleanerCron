<?php
declare(strict_types=1);

require_once __DIR__ . '/Lock/interface.ilUserCleanerRunLock.php';
require_once __DIR__ . '/Lock/class.ilUserCleanerRunLockRepository.php';

final class ilUserCleanerCronRunState
{
    public const JOB_ID = 'ucc';

    public function __construct(
        private readonly ilCronJobRepository $cronRepository,
        private readonly ?ilUserCleanerRunLock $runLock = null
    ) {
    }

    public static function fromDIC(): self
    {
        global $DIC;

        return new self(
            $DIC->cron()->repository(),
            new ilUserCleanerRunLockRepository()
        );
    }

    public function isRunning(): bool
    {
        if ($this->runLock?->isLocked() === true) {
            return true;
        }

        $jobs = $this->cronRepository->getCronJobData(self::JOB_ID);
        $job = $jobs[0] ?? null;

        return is_array($job)
            && (
                (int) ($job['running_ts'] ?? 0) > 0
                || (int) ($job['alive_ts'] ?? 0) > 0
            );
    }
}
