<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerJobLockTest extends TestCase
{
    private bool $hadDIC;
    private mixed $previousDIC;

    protected function setUp(): void
    {
        $this->hadDIC = array_key_exists('DIC', $GLOBALS);
        $this->previousDIC = $GLOBALS['DIC'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadDIC) {
            $GLOBALS['DIC'] = $this->previousDIC;
            return;
        }
        unset($GLOBALS['DIC']);
    }

    public function testCompetingRunStopsBeforeHeartbeatOrWork(): void
    {
        $logger = $this->createMock(ilLogger::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('UserCleaner did not start because another run owns the execution lock.');
        $GLOBALS['DIC'] = new ilUserCleanerJobTestContainer($logger);

        $run_lock = $this->createMock(ilUserCleanerRunLock::class);
        $run_lock->expects(self::once())
            ->method('acquire')
            ->willReturn(false);
        $run_lock->expects(self::never())->method('heartbeat');
        $run_lock->expects(self::never())->method('release');

        $result = (new ilUserCleanerJob($run_lock))->run();

        self::assertSame(ilCronJobResult::STATUS_FAIL, $result->getStatus());
        self::assertStringContainsString('another run owns', $result->getMessage());
    }

    public function testHandledFailureReleasesTheOwnedLock(): void
    {
        $logger = $this->createMock(ilLogger::class);
        $GLOBALS['DIC'] = new ilUserCleanerJobTestContainer($logger, true);

        $run_lock = $this->createMock(ilUserCleanerRunLock::class);
        $run_lock->expects(self::once())
            ->method('acquire')
            ->willReturn(true);
        $run_lock->expects(self::once())->method('heartbeat');
        $run_lock->expects(self::once())
            ->method('release')
            ->willReturn(true);

        $result = (new ilUserCleanerJob($run_lock))->run();

        self::assertSame(ilCronJobResult::STATUS_FAIL, $result->getStatus());
        self::assertSame('Cron service unavailable', $result->getMessage());
    }
}

final class ilUserCleanerJobTestContainer
{
    public function __construct(
        private readonly ilLogger $logger,
        private readonly bool $failCron = false
    ) {
    }

    public function logger(): object
    {
        return new class ($this->logger) {
            public function __construct(private readonly ilLogger $logger)
            {
            }

            public function user(): ilLogger
            {
                return $this->logger;
            }
        };
    }

    public function cron(): object
    {
        if ($this->failCron) {
            throw new RuntimeException('Cron service unavailable');
        }

        throw new LogicException('Cron service must not be called when lock acquisition fails.');
    }
}
