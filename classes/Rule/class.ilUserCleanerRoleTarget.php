<?php
declare(strict_types=1);

final class ilUserCleanerRoleTarget
{
    public function __construct(
        public readonly int $roleId,
        public readonly string $title,
        public readonly string $description
    ) {
        if ($this->roleId <= 0) {
            throw new InvalidArgumentException('A role target requires a positive role ID.');
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['role_id'],
            (string) $row['title'],
            (string) $row['description']
        );
    }
}
