<?php
declare(strict_types=1);

final class ilUserCleanerExclusionRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE = 'ucc_exclusion';

    /** @return array<int, array{exclusion_id: int|string, user_id: int|string}> */
    public function getAll(): array
    {
        return $this->fetchAll('SELECT exclusion_id, user_id FROM ' . self::TABLE . ' ORDER BY exclusion_id');
    }

    public function containsUser(int $user_id): bool
    {
        return $this->fetchRow(
            'SELECT exclusion_id FROM ' . self::TABLE . ' WHERE user_id = %s',
            ['integer'],
            [$user_id]
        ) !== null;
    }

    public function addUser(int $user_id): void
    {
        if ($this->containsUser($user_id)) {
            return;
        }
        $this->database->insert(self::TABLE, [
            'exclusion_id' => ['integer', $this->database->nextId(self::TABLE)],
            'user_id' => ['integer', $user_id],
        ]);
    }

    public function delete(int $id): void
    {
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE . ' WHERE exclusion_id = %s',
            ['integer'],
            [$id]
        );
    }
}
