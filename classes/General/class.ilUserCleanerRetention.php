<?php
declare(strict_types=1);

final class ilUserCleanerRetention
{
    public function __construct(
        public readonly int $value,
        public readonly ilUserCleanerRetentionUnit $unit
    ) {
        if ($this->value <= 0) {
            throw new InvalidArgumentException('The retention period must be greater than zero.');
        }
    }

    public function getCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->sub(new DateInterval(
            $this->unit === ilUserCleanerRetentionUnit::DAYS
                ? 'P' . $this->value . 'D'
                : 'P' . $this->value . 'M'
        ));
    }
}
