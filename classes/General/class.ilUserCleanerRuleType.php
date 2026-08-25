<?php
declare(strict_types=1);

final class ilUserCleanerRuleType
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly ilUserCleanerRuleSource $source,
        public readonly bool $valueRequired,
        public readonly bool $configurationRequired
    ) {
        if ($this->id <= 0 || trim($this->key) === '') {
            throw new InvalidArgumentException('A rule type requires an ID and key.');
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['parameter_id'],
            (string) $row['parameter'],
            ilUserCleanerRuleSource::from((string) $row['source_type']),
            (bool) $row['value_required'],
            (bool) $row['configuration_required']
        );
    }
}
