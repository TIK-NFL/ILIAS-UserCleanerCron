<?php
declare(strict_types=1);

final class ilUserCleanerLDAPSource
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $active,
        public readonly bool $authentication,
        public readonly int $authenticationType
    ) {
        if ($this->id <= 0) {
            throw new InvalidArgumentException('An LDAP source requires a positive server ID.');
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['server_id'],
            (string) $row['name'],
            (bool) $row['active'],
            (bool) $row['authentication'],
            (int) $row['authentication_type']
        );
    }

    public function isDataSource(): bool
    {
        return !$this->authentication || $this->authenticationType > 0;
    }
}
