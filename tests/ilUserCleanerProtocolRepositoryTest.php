<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerProtocolRepositoryTest extends TestCase
{
    public function testSnapshotIsInsertedOnceAndOutcomeUpdatesTheSameRow(): void
    {
        $db = $this->createMock(ilDBInterface::class);
        $db->method('nextId')->with('ucc_protocol')->willReturn(42);
        $db->expects(self::once())->method('insert')->with('ucc_protocol', self::callback(static function (array $values): bool {
            return $values['protocol_id'][1] === 42
                && $values['user_id'][1] === 100
                && $values['external_account'][1] === 'external-100'
                && $values['status'][1] === 'pending';
        }))->willReturn(1);
        $statuses = [];
        $db->expects(self::exactly(2))->method('update')->willReturnCallback(
            static function (string $table, array $values, array $where) use (&$statuses): int {
                self::assertSame('ucc_protocol', $table);
                self::assertSame(['protocol_id' => ['integer', 42]], $where);
                $statuses[] = $values['status'][1];
                return 1;
            }
        );
        $user = $this->createMock(ilObjUser::class);
        $user->method('getId')->willReturn(100);
        $user->method('getExternalAccount')->willReturn('external-100');
        $repository = new ilUserCleanerProtocolRepository($db);
        $id = $repository->start($user, ilUserCleanerCleanupAction::DELETE, []);
        $repository->finish($id, 'deleting');
        $repository->finish($id, 'success');
        self::assertSame(['deleting', 'success'], $statuses);
    }

    public function testEarlierDeletionIsFoundRegardlessOfOutcome(): void
    {
        $db = $this->createMock(ilDBInterface::class);
        $db->expects(self::once())->method('queryF')->with(
            'SELECT protocol_id FROM ucc_protocol WHERE user_id = %s AND action = %s',
            ['integer', 'text'],
            [100, 'delete']
        )->willReturn($this->createMock(ilDBStatement::class));
        $db->method('fetchAssoc')->willReturn(['protocol_id' => 42]);
        self::assertTrue((new ilUserCleanerProtocolRepository($db))->hasDeleteAttempt(100));
    }

    public function testRetentionKeepsUnresolvedAndFailedDeletions(): void
    {
        $db = $this->createMock(ilDBInterface::class);
        $db->expects(self::once())->method('manipulateF')->with(
            'DELETE FROM ucc_protocol WHERE created_at < %s AND NOT (action = %s AND status <> %s)',
            ['timestamp', 'text', 'text'],
            ['2026-01-01 00:00:00', 'delete', 'success']
        );
        (new ilUserCleanerProtocolRepository($db))->deleteOlderThan(new DateTimeImmutable('2026-01-01 00:00:00'));
    }
}
