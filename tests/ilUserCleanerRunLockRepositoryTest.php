<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerRunLockRepositoryTest extends TestCase
{
    public function testAtomicClaimHasExactlyOneWinner(): void
    {
        $database = $this->createMock(ilDBInterface::class);
        $database->expects(self::exactly(2))
            ->method('manipulateF')
            ->with(
                self::stringContains('WHERE lock_id = %s AND owner_token = %s'),
                ['text', 'integer', 'integer', 'text', 'integer', 'integer', 'text'],
                self::callback(static fn(array $values): bool => $values[5] === 1 && $values[6] === '')
            )
            ->willReturnOnConsecutiveCalls(1, 0);

        $repository = new ilUserCleanerRunLockRepository($database);

        self::assertTrue($repository->acquire('owner-a', 100, 'node-a', 10));
        self::assertFalse($repository->acquire('owner-b', 100, 'node-b', 20));
    }

    public function testHeartbeatAcceptsAnUnchangedTimestampWhileOwnershipRemains(): void
    {
        $database = $this->createMock(ilDBInterface::class);
        $database->expects(self::once())->method('manipulateF')->willReturn(0);
        $database->expects(self::once())->method('queryF')->willReturn($this->createMock(ilDBStatement::class));
        $database->expects(self::once())->method('fetchAssoc')->willReturn(['owner_token' => 'owner-a']);

        (new ilUserCleanerRunLockRepository($database))->heartbeat('owner-a', 100);

        self::addToAssertionCount(1);
    }

    public function testHeartbeatFailsWhenOwnershipWasLost(): void
    {
        $database = $this->createMock(ilDBInterface::class);
        $database->expects(self::once())->method('manipulateF')->willReturn(0);
        $database->expects(self::once())->method('queryF')->willReturn($this->createMock(ilDBStatement::class));
        $database->expects(self::once())->method('fetchAssoc')->willReturn(['owner_token' => 'owner-b']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('execution lock ownership was lost');

        (new ilUserCleanerRunLockRepository($database))->heartbeat('owner-a', 100);
    }

    public function testOnlyTheOwnerCanReleaseTheLock(): void
    {
        $database = $this->createMock(ilDBInterface::class);
        $database->expects(self::exactly(2))
            ->method('manipulateF')
            ->with(
                self::stringContains('WHERE lock_id = %s AND owner_token = %s'),
                ['text', 'integer', 'integer', 'text', 'integer', 'integer', 'text'],
                self::callback(static fn(array $values): bool => $values[5] === 1)
            )
            ->willReturnOnConsecutiveCalls(0, 1);

        $repository = new ilUserCleanerRunLockRepository($database);

        self::assertFalse($repository->release('owner-b'));
        self::assertTrue($repository->release('owner-a'));
    }
}
