<?php
declare(strict_types=1);

final class ilUserCleanerLDAPAccountChecker implements ilUserCleanerLDAPAccountLookup
{
    /** @var array<int, array{server: ilLDAPServer, query: ilLDAPQuery}> */
    private array $connections = [];
    private ilUserCleanerLDAPSourceRepository $sources;

    public function __construct()
    {
        $this->sources = new ilUserCleanerLDAPSourceRepository();
    }

    public function accountExists(string $configuration_id, string $account): bool
    {
        $server_id = $this->getServerId($configuration_id);
        $connection = $this->getConnection($server_id);
        $server = $connection['server'];
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
            return $connection['query']->query(
                strtolower($search_base),
                $filter,
                $server->getUserScope(),
                [$server->getUserAttribute()]
            )->numRows() > 0;
        } catch (Throwable $exception) {
            throw new RuntimeException('LDAP account lookup failed.', 0, $exception);
        }
    }

    public function assertConfigurationAvailable(string $configuration_id): void
    {
        $this->getConnection($this->getServerId($configuration_id));
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

    /** @return array{server: ilLDAPServer, query: ilLDAPQuery} */
    private function getConnection(int $server_id): array
    {
        if (isset($this->connections[$server_id])) {
            return $this->connections[$server_id];
        }

        $source = $this->sources->getById($server_id);
        if ($source === null || !$source->active) {
            throw new RuntimeException('The selected LDAP source is missing or inactive.');
        }

        try {
            $server = ilLDAPServer::getInstanceByServerId($server_id);
            if ($server->getUserAttribute() === '' || $server->getBaseDN() === '') {
                throw new RuntimeException('The selected LDAP source is incomplete.');
            }
            $query = new ilLDAPQuery($server);
            $query->bind();
        } catch (Throwable $exception) {
            throw new RuntimeException('LDAP connection failed.', 0, $exception);
        }

        return $this->connections[$server_id] = ['server' => $server, 'query' => $query];
    }
}
