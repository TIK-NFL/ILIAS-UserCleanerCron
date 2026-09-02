<?php
declare(strict_types=1);

final class ilUserCleanerRule
{
    public function __construct(
        public readonly int $id,
        public readonly int $parameterId,
        public readonly string $parameter,
        public readonly string $symbol,
        public readonly int $value,
        public readonly ilUserCleanerRuleSource $source,
        public readonly bool $valueRequired,
        public readonly bool $configurationRequired,
        public readonly ?string $sourceConfigId
    ) {
        if ($this->id < 0 || $this->parameterId <= 0) {
            throw new InvalidArgumentException('A rule requires a valid parameter.');
        }
        if (!isset(ilUserCleanerGUIConstants::RULE_SYMBOLS[$this->symbol])) {
            throw new InvalidArgumentException('Unsupported rule comparison symbol: ' . $this->symbol);
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['rule_id'],
            (int) $row['parameter_id'],
            (string) $row['parameter'],
            (string) $row['symbol'],
            (int) $row['value'],
            ilUserCleanerRuleSource::from((string) $row['source_type']),
            (bool) $row['value_required'],
            (bool) $row['configuration_required'],
            isset($row['source_config_id']) ? (string) $row['source_config_id'] : null
        );
    }
}
