<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repository/class.ilUserCleanerDatabaseRepository.php';
require_once __DIR__ . '/interface.ilUserCleanerRunLock.php';

final class ilUserCleanerRunLockRepository extends ilUserCleanerDatabaseRepository implements ilUserCleanerRunLock
{
    private const TABLE = 'ucc_run_lock';
    private const LOCK_ID = 1;

    public function acquire(
        string $owner_token,
        int $timestamp,
        string $hostname,
        int $process_id
    ): bool {
        $this->assertOwnerToken($owner_token);

        return $this->database->manipulateF(
            'UPDATE ' . self::TABLE . ' SET owner_token = %s, started_at = %s, heartbeat_at = %s, ' .
            'hostname = %s, process_id = %s WHERE lock_id = %s AND owner_token = %s',
            ['text', 'integer', 'integer', 'text', 'integer', 'integer', 'text'],
            [
                $owner_token,
                $timestamp,
                $timestamp,
                mb_substr($hostname, 0, 255),
                $process_id,
                self::LOCK_ID,
                '',
            ]
        ) === 1;
    }

    public function heartbeat(string $owner_token, int $timestamp): void
    {
        $this->assertOwnerToken($owner_token);

        $affected_rows = $this->database->manipulateF(
            'UPDATE ' . self::TABLE . ' SET heartbeat_at = %s WHERE lock_id = %s AND owner_token = %s',
            ['integer', 'integer', 'text'],
            [$timestamp, self::LOCK_ID, $owner_token]
        );
        if ($affected_rows === 1 || $this->isOwnedBy($owner_token)) {
            return;
        }

        throw new RuntimeException('UserCleaner execution lock ownership was lost.');
    }

    public function release(string $owner_token): bool
    {
        $this->assertOwnerToken($owner_token);

        return $this->database->manipulateF(
            'UPDATE ' . self::TABLE . ' SET owner_token = %s, started_at = %s, heartbeat_at = %s, ' .
            'hostname = %s, process_id = %s WHERE lock_id = %s AND owner_token = %s',
            ['text', 'integer', 'integer', 'text', 'integer', 'integer', 'text'],
            ['', 0, 0, '', 0, self::LOCK_ID, $owner_token]
        ) === 1;
    }

    public function isLocked(): bool
    {
        $row = $this->getLockRow();

        return $row !== null && trim((string) ($row['owner_token'] ?? '')) !== '';
    }

    private function isOwnedBy(string $owner_token): bool
    {
        $row = $this->getLockRow();

        return $row !== null && hash_equals((string) ($row['owner_token'] ?? ''), $owner_token);
    }

    /** @return array<string, mixed>|null */
    private function getLockRow(): ?array
    {
        return $this->fetchRow(
            'SELECT owner_token FROM ' . self::TABLE . ' WHERE lock_id = %s',
            ['integer'],
            [self::LOCK_ID]
        );
    }

    private function assertOwnerToken(string $owner_token): void
    {
        if ($owner_token === '' || strlen($owner_token) > 64) {
            throw new InvalidArgumentException('A non-empty execution lock owner token of at most 64 bytes is required.');
        }
    }
}
