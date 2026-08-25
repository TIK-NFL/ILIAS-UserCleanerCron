<?php
declare(strict_types=1);

final class ilUserCleanerSettingsRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE = 'ucc_config';
    private const DRY_RUN = 'dry_run';
    private const CLEANUP_ACTION = 'cleanup_action';
    private const RETENTION_VALUE = 'protocol_retention_value';
    private const RETENTION_UNIT = 'protocol_retention_unit';

    public function isDryRun(): bool
    {
        $value = $this->getValue(self::DRY_RUN);

        return $value === null || $value === '1';
    }

    public function saveDryRun(bool $dry_run): void
    {
        $this->setValue(self::DRY_RUN, $dry_run ? '1' : '0');
    }

    public function getCleanupAction(): ilUserCleanerCleanupAction
    {
        return ilUserCleanerCleanupAction::tryFrom(
            $this->getValue(self::CLEANUP_ACTION) ?? ''
        ) ?? ilUserCleanerCleanupAction::DEACTIVATE;
    }

    public function saveCleanupAction(ilUserCleanerCleanupAction $action): void
    {
        $this->setValue(self::CLEANUP_ACTION, $action->value);
    }

    public function getProtocolRetention(): ?ilUserCleanerRetention
    {
        $value = $this->getValue(self::RETENTION_VALUE);
        $unit = $this->getValue(self::RETENTION_UNIT);
        if ($value === null || $unit === null || !ctype_digit($value) || (int) $value <= 0) {
            return null;
        }

        $retention_unit = ilUserCleanerRetentionUnit::tryFrom($unit);
        return $retention_unit === null ? null : new ilUserCleanerRetention((int) $value, $retention_unit);
    }

    public function saveProtocolRetention(ilUserCleanerRetention $retention): void
    {
        $this->setValue(self::RETENTION_VALUE, (string) $retention->value);
        $this->setValue(self::RETENTION_UNIT, $retention->unit->value);
    }

    public function deleteProtocolRetention(): void
    {
        $this->deleteValue(self::RETENTION_VALUE);
        $this->deleteValue(self::RETENTION_UNIT);
    }

    private function getValue(string $key): ?string
    {
        $key_column = $this->database->quoteIdentifier('key');
        $row = $this->fetchRow(
            'SELECT value FROM ' . self::TABLE . ' WHERE ' . $key_column . ' = %s',
            ['text'],
            [$key]
        );
        return isset($row['value']) ? (string) $row['value'] : null;
    }

    private function setValue(string $key, string $value): void
    {
        $values = ['key' => ['text', $key], 'value' => ['clob', $value]];
        if ($this->getValue($key) === null) {
            $this->database->insert(
                self::TABLE,
                ['config_id' => ['integer', $this->database->nextId(self::TABLE)]] + $values
            );
            return;
        }
        $this->database->update(self::TABLE, $values, ['key' => ['text', $key]]);
    }

    private function deleteValue(string $key): void
    {
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE . ' WHERE ' . $this->database->quoteIdentifier('key') . ' = %s',
            ['text'],
            [$key]
        );
    }
}
