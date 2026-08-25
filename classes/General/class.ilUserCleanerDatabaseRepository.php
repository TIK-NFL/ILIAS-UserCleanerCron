<?php
declare(strict_types=1);

abstract class ilUserCleanerDatabaseRepository
{
    protected ilDBInterface $database;

    public function __construct(?ilDBInterface $database = null)
    {
        global $DIC;

        $this->database = $database ?? $DIC->database();
    }

    protected function fetchRow(string $sql, array $types = [], array $values = []): ?array
    {
        $result = $types === []
            ? $this->database->query($sql)
            : $this->database->queryF($sql, $types, $values);
        $row = $this->database->fetchAssoc($result);

        return $row ?: null;
    }

    protected function fetchAll(string $sql, array $types = [], array $values = []): array
    {
        $result = $types === []
            ? $this->database->query($sql)
            : $this->database->queryF($sql, $types, $values);

        return $this->database->fetchAll($result);
    }
}
