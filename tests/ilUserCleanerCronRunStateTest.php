<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerCronRunStateTest extends TestCase
{
    public function testMissingCronRecordIsNotRunning(): void
    {
        self::assertFalse($this->state([])->isRunning());
    }

    public function testPluginExecutionLockIsAuthoritative(): void
    {
        self::assertTrue($this->state([], true)->isRunning());
    }

    public function testClearedRunInformationIsNotRunning(): void
    {
        self::assertFalse($this->state([[
            'running_ts' => 0,
            'alive_ts' => 0,
        ]])->isRunning());
    }

    /** @dataProvider runningInformation */
    public function testEitherRunTimestampLocksRuleConfiguration(int $running_ts, int $alive_ts): void
    {
        self::assertTrue($this->state([[
            'running_ts' => $running_ts,
            'alive_ts' => $alive_ts,
        ]])->isRunning());
    }

    public static function runningInformation(): array
    {
        return [
            'running timestamp' => [100, 0],
            'heartbeat timestamp' => [0, 100],
            'normal running state' => [100, 120],
        ];
    }

    private function state(array $jobs, bool $locked = false): ilUserCleanerCronRunState
    {
        $repository = $this->createMock(ilCronJobRepository::class);
        if ($locked) {
            $repository->expects(self::never())->method('getCronJobData');
        } else {
            $repository->expects(self::once())
                ->method('getCronJobData')
                ->with(ilUserCleanerCronRunState::JOB_ID)
                ->willReturn($jobs);
        }

        $run_lock = $this->createMock(ilUserCleanerRunLock::class);
        $run_lock->expects(self::once())->method('isLocked')->willReturn($locked);

        return new ilUserCleanerCronRunState($repository, $run_lock);
    }
}
