<?php
declare(strict_types=1);

interface ilUserCleanerLDAPAccountLookup
{
    public function assertConfigurationAvailable(string $configuration_id): void;

    public function accountExists(string $configuration_id, string $account): bool;
}
