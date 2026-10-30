<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ilUserCleanerActionExecutorTest extends TestCase
{
    public function testEachUserIsRecordedDisabledAndDeletedBeforeTheNextStarts(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $users = [100 => $this->user(100, $protocol), 101 => $this->user(101, $protocol)];
        $pings = 0;
        $result = $this->executor($protocol, $users)->execute(
            [100 => [], 101 => []],
            ilUserCleanerCleanupAction::DELETE,
            static function () use (&$pings): void { ++$pings; }
        );
        self::assertSame(2, $result->changed);
        self::assertSame(0, $result->failed);
        self::assertSame([
            'start:100', 'disable:100', 'update:100', 'sessions:100', 'deleting:100', 'delete:100', 'success:100',
            'start:101', 'disable:101', 'update:101', 'sessions:101', 'deleting:101', 'delete:101', 'success:101',
        ], $protocol->events);
        self::assertSame(4, $pings);
        self::assertCount(2, $protocol->records);
    }

    public function testDeactivationFailurePreventsDeletion(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $user = $this->user(100, $protocol, update_ok: false);
        $user->expects(self::never())->method('delete');
        $result = $this->executor($protocol, [100 => $user])->execute([100 => []], ilUserCleanerCleanupAction::DELETE);
        self::assertSame(1, $result->failed);
        self::assertSame(['start:100', 'disable:100', 'update:100', 'failed:100'], $protocol->events);
    }

    public function testAlreadyDisabledUserIsStillDeleted(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $user = $this->user(100, $protocol, active: false);
        $user->expects(self::never())->method('update');
        $result = $this->executor($protocol, [100 => $user])->execute([100 => []], ilUserCleanerCleanupAction::DELETE);
        self::assertSame(1, $result->changed);
        self::assertSame(['start:100', 'sessions:100', 'deleting:100', 'delete:100', 'success:100'], $protocol->events);
    }

    public function testFailedDeletionKeepsOneRecordAndIsNotRetried(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $user = $this->user(100, $protocol, delete_ok: false);
        $executor = $this->executor($protocol, [100 => $user]);
        self::assertSame(1, $executor->execute([100 => []], ilUserCleanerCleanupAction::DELETE)->failed);
        $events = $protocol->events;
        self::assertSame('failed', $protocol->records[100]);
        self::assertSame(1, $executor->execute([100 => []], ilUserCleanerCleanupAction::DELETE)->failed);
        self::assertSame($events, $protocol->events);
        self::assertCount(1, $protocol->records);
    }

    #[DataProvider('priorOutcomes')]
    public function testExistingDeletionIsNeitherRepeatedNorDuplicated(string $status): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $protocol->records[100] = $status;
        $user = $this->createMock(ilObjUser::class);
        $user->expects(self::never())->method('delete');
        $user->expects(self::never())->method('update');
        $result = $this->executor($protocol, [100 => $user])->execute([100 => []], ilUserCleanerCleanupAction::DELETE);
        self::assertSame(1, $result->failed);
        self::assertSame([100 => $status], $protocol->records);
        self::assertSame([], $protocol->events);
    }

    public static function priorOutcomes(): array
    {
        return [['pending'], ['deleting'], ['failed'], ['success']];
    }

    public function testFailedSuccessWriteDoesNotClaimDeletionFailed(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $protocol->failSuccess = true;
        $user = $this->user(100, $protocol);
        $result = $this->executor($protocol, [100 => $user])->execute([100 => []], ilUserCleanerCleanupAction::DELETE);
        self::assertSame(1, $result->failed);
        self::assertSame(1, $result->changed);
        self::assertSame('deleting', $protocol->records[100]);
        self::assertNotContains('failed:100', $protocol->events);
        self::assertContains('delete:100', $protocol->events);
    }

    public function testInitialProtocolFailureLeavesUserUntouched(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $protocol->failStart = true;
        $user = $this->createMock(ilObjUser::class);
        $user->expects(self::never())->method('setActive');
        $user->expects(self::never())->method('delete');
        $result = $this->executor($protocol, [100 => $user])->execute([100 => []], ilUserCleanerCleanupAction::DELETE);
        self::assertSame(1, $result->failed);
        self::assertSame([], $protocol->records);
    }

    public function testDeactivateActionDoesNotDelete(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $user = $this->user(100, $protocol);
        $user->expects(self::never())->method('delete');
        $result = $this->executor($protocol, [100 => $user])->execute([100 => []], ilUserCleanerCleanupAction::DEACTIVATE);
        self::assertSame(1, $result->changed);
        self::assertSame(['start:100', 'disable:100', 'update:100', 'success:100'], $protocol->events);
    }

    public function testSessionInvalidationFailurePreventsDeletion(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $user = $this->user(100, $protocol);
        $user->expects(self::never())->method('delete');
        $result = $this->executor($protocol, [100 => $user], false)->execute([100 => []], ilUserCleanerCleanupAction::DELETE);
        self::assertSame(1, $result->failed);
        self::assertSame(['start:100', 'disable:100', 'update:100', 'sessions:100', 'failed:100'], $protocol->events);
    }

    public function testExceptionDuringDeletionIsRecordedBeforeContinuing(): void
    {
        $protocol = new ilUserCleanerTestActionProtocol();
        $user = $this->createMock(ilObjUser::class);
        $user->method('getId')->willReturn(100);
        $user->method('getActive')->willReturn(false);
        $user->method('delete')->willThrowException(new RuntimeException('Deletion interrupted'));
        $result = $this->executor($protocol, [100 => $user, 101 => $this->user(101, $protocol)])
            ->execute([100 => [], 101 => []], ilUserCleanerCleanupAction::DELETE);
        self::assertSame(1, $result->failed);
        self::assertSame(1, $result->changed);
        self::assertSame([100 => 'failed', 101 => 'success'], $protocol->records);
        self::assertLessThan(array_search('start:101', $protocol->events), array_search('failed:100', $protocol->events));
    }

    private function user(int $id, ilUserCleanerTestActionProtocol $protocol, bool $active = true, bool $update_ok = true, bool $delete_ok = true): ilObjUser
    {
        $user = $this->createMock(ilObjUser::class);
        $user->method('getId')->willReturn($id);
        $user->method('getActive')->willReturn($active);
        $user->method('setActive')->willReturnCallback(static function (bool $active, int $actor) use ($id, $protocol): void {
            self::assertFalse($active);
            self::assertSame(6, $actor);
            $protocol->events[] = 'disable:' . $id;
        });
        $user->method('update')->willReturnCallback(static function () use ($id, $protocol, $update_ok): bool {
            $protocol->events[] = 'update:' . $id;
            return $update_ok;
        });
        $user->method('delete')->willReturnCallback(static function () use ($id, $protocol, $delete_ok): bool {
            $protocol->events[] = 'delete:' . $id;
            return $delete_ok;
        });
        return $user;
    }

    private function executor(ilUserCleanerTestActionProtocol $protocol, array $users, bool $sessions_ok = true): ilUserCleanerActionExecutor
    {
        $db = $this->createMock(ilDBInterface::class);
        $db->method('fetchAssoc')->willReturn(null);
        $rbac = $this->createMock(ilRbacReview::class);
        $rbac->method('isAssigned')->willReturn(false);
        return new ilUserCleanerActionExecutor(
            new ilUserCleanerExclusionRepository($db),
            $this->createMock(ilLogger::class),
            6,
            $protocol,
            $rbac,
            static fn(int $id) => $users[$id] ?? null,
            static function (int $id) use ($protocol, $sessions_ok): bool {
                $protocol->events[] = 'sessions:' . $id;
                return $sessions_ok;
            }
        );
    }
}

final class ilUserCleanerTestActionProtocol implements ilUserCleanerActionProtocol
{
    public array $events = [];
    public array $records = [];
    public bool $failSuccess = false;
    public bool $failStart = false;

    public function hasDeleteAttempt(int $user_id): bool
    {
        return isset($this->records[$user_id]);
    }

    public function start(ilObjUser $user, ilUserCleanerCleanupAction|string $action, array $matched_rule_set_ids): int
    {
        if ($this->failStart) {
            throw new RuntimeException('Protocol unavailable');
        }
        $id = $user->getId();
        $this->events[] = 'start:' . $id;
        $this->records[$id] = 'pending';
        return $id;
    }

    public function finish(int $protocol_id, string $status, ?string $error_message = null): void
    {
        if ($this->failSuccess && $status === 'success') {
            throw new RuntimeException('Protocol update unavailable');
        }
        $this->events[] = $status . ':' . $protocol_id;
        $this->records[$protocol_id] = $status;
    }
}
