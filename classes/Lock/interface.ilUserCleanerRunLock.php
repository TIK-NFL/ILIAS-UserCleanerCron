<?php
declare(strict_types=1);

interface ilUserCleanerRunLock
{
    public function acquire(
        string $owner_token,
        int $timestamp,
        string $hostname,
        int $process_id
    ): bool;

    public function heartbeat(string $owner_token, int $timestamp): void;

    public function release(string $owner_token): bool;

    public function isLocked(): bool;
}
