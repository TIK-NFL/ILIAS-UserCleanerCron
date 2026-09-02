<?php
declare(strict_types=1);

final class ilUserCleanerAuthModeRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE = 'ucc_auth';

    /** @return array<int, array{auth_id: int|string, auth_mode: string}> */
    public function getAll(): array
    {
        return $this->fetchAll('SELECT auth_id, auth_mode FROM ' . self::TABLE . ' ORDER BY auth_mode');
    }

    public function getModeById(int $id): ?string
    {
        $row = $this->fetchRow(
            'SELECT auth_mode FROM ' . self::TABLE . ' WHERE auth_id = %s',
            ['integer'],
            [$id]
        );

        return isset($row['auth_mode']) ? (string) $row['auth_mode'] : null;
    }
}
