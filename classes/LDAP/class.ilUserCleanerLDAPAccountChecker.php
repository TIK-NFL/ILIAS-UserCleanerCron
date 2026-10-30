<?php
declare(strict_types=1);

final class ilUserCleanerLDAPAccountChecker implements ilUserCleanerLDAPAccountLookup
{
    private ilUserCleanerLDAPSourceRepository $sources;
    private ilUserCleanerLDAPDirectory $directory;
    private Closure $loadServer;

    public function __construct(
        ?ilUserCleanerLDAPSourceRepository $sources = null,
        ?ilUserCleanerLDAPDirectory $directory = null,
        ?Closure $load_server = null
    ) {
        $this->sources = $sources ?? new ilUserCleanerLDAPSourceRepository();
        $this->directory = $directory ?? new ilUserCleanerNativeLDAPDirectory();
        $this->loadServer = $load_server
            ?? static fn(int $id): ilLDAPServer => ilLDAPServer::getInstanceByServerId($id);
    }

    public function accountExists(string $configuration_id, string $account): bool
    {
        $server_id = $this->getServerId($configuration_id);
        $server = $this->getServer($server_id);
        $search_base = $server->getSearchBase();
        if ($search_base !== '' && !str_ends_with($search_base, ',')) {
            $search_base .= ',';
        }
        $search_base .= $server->getBaseDN();

        $filter = sprintf(
            '(&(%s=%s)%s)',
            $server->getUserAttribute(),
            ldap_escape($account, '', LDAP_ESCAPE_FILTER),
            $server->getFilter()
        );

        try {
            return $this->directory->hasEntry(
                $server,
                $search_base,
                $filter,
                [$server->getUserAttribute()]
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'LDAP account lookup failed; the account was not classified as missing.',
                0,
                $exception
            );
        }
    }

    public function assertConfigurationAvailable(string $configuration_id): void
    {
        $this->directory->assertAvailable($this->getServer($this->getServerId($configuration_id)));
    }

    private function getServerId(string $configuration_id): int
    {
        if (!preg_match('/^ldap:(\d+)$/', $configuration_id, $matches)) {
            throw new RuntimeException('Invalid LDAP source configuration.');
        }
        if (!function_exists('ldap_escape')) {
            throw new RuntimeException('The PHP LDAP extension is not available.');
        }

        return (int) $matches[1];
    }

    private function getServer(int $server_id): ilLDAPServer
    {
        $source = $this->sources->getById($server_id);
        if ($source === null || !$source->active) {
            throw new RuntimeException('The selected LDAP source is missing or inactive.');
        }

        try {
            $server = ($this->loadServer)($server_id);
            if ($server->getUserAttribute() === '' || $server->getBaseDN() === '') {
                throw new RuntimeException('The selected LDAP source is incomplete.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('LDAP source configuration could not be loaded.', 0, $exception);
        }

        return $server;
    }
}
