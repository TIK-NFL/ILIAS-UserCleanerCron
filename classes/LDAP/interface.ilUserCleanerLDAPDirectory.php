<?php
declare(strict_types=1);

interface ilUserCleanerLDAPDirectory
{
    public function assertAvailable(ilLDAPServer $server): void;

    public function hasEntry(
        ilLDAPServer $server,
        string $search_base,
        string $filter,
        array $attributes
    ): bool;
}
