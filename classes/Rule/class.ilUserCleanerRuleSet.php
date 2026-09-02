<?php
declare(strict_types=1);

final class ilUserCleanerRuleSet
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly int $roleId,
        public readonly int $authId,
        public readonly bool $enabled
    ) {
        if ($this->id < 0 || $this->roleId <= 0 || $this->authId <= 0 || trim($this->title) === '') {
            throw new InvalidArgumentException('A rule set requires a title, target role and authentication method.');
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['rule_set_id'],
            (string) $row['title'],
            (string) $row['description'],
            (int) $row['role_id'],
            (int) $row['auth_id'],
            (bool) $row['enabled']
        );
    }
}
