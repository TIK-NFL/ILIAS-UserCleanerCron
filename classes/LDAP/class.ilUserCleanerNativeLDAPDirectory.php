<?php
declare(strict_types=1);

final class ilUserCleanerNativeLDAPDirectory implements ilUserCleanerLDAPDirectory
{
    private const SEARCH_TIMEOUT_SECONDS = 30;
    private const LDAP_SUCCESS_RESULT_CODE = 0;

    /** @var array<int, mixed> */
    private array $connections = [];

    public function assertAvailable(ilLDAPServer $server): void
    {
        $this->getConnection($server);
    }

    public function hasEntry(
        ilLDAPServer $server,
        string $search_base,
        string $filter,
        array $attributes
    ): bool {
        $connection = $this->getConnection($server);

        try {
            $result = $this->withWarningsAsExceptions(fn() => match ($server->getUserScope()) {
                ilLDAPServer::LDAP_SCOPE_SUB => ldap_search(
                    $connection,
                    $search_base,
                    $filter,
                    $attributes,
                    0,
                    0,
                    self::SEARCH_TIMEOUT_SECONDS,
                    LDAP_DEREF_NEVER
                ),
                ilLDAPServer::LDAP_SCOPE_ONE => ldap_list(
                    $connection,
                    $search_base,
                    $filter,
                    $attributes,
                    0,
                    0,
                    self::SEARCH_TIMEOUT_SECONDS,
                    LDAP_DEREF_NEVER
                ),
                ilLDAPServer::LDAP_SCOPE_BASE => ldap_read(
                    $connection,
                    $search_base,
                    $filter,
                    $attributes,
                    0,
                    0,
                    self::SEARCH_TIMEOUT_SECONDS,
                    LDAP_DEREF_NEVER
                ),
                default => throw new RuntimeException('Unsupported LDAP search scope.'),
            });
            if ($result === false) {
                throw new RuntimeException('LDAP search returned no result handle.');
            }

            $result_code = null;
            if (
                !ldap_parse_result($connection, $result, $result_code)
                || $result_code !== self::LDAP_SUCCESS_RESULT_CODE
            ) {
                $message = $result_code === null
                    ? 'could not read the LDAP result status'
                    : ldap_err2str($result_code);
                throw new RuntimeException('LDAP search was not successful: ' . $message);
            }

            return ldap_count_entries($connection, $result) > 0;
        } catch (Throwable $exception) {
            $this->dropConnection($server->getServerId());
            throw new RuntimeException('LDAP search failed; account absence was not confirmed.', 0, $exception);
        } finally {
            if (isset($result) && $result !== false) {
                ldap_free_result($result);
            }
        }
    }

    private function getConnection(ilLDAPServer $server): mixed
    {
        $server_id = $server->getServerId();
        if (isset($this->connections[$server_id])) {
            return $this->connections[$server_id];
        }

        $url = trim($server->getUrl());
        if ($url === '') {
            throw new RuntimeException('The selected LDAP source has no current endpoint URL.');
        }

        try {
            return $this->connections[$server_id] = $this->connectAndBind($server, $url);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The current ILIAS LDAP endpoint could not be reached or bound; no fallback was attempted.',
                0,
                $exception
            );
        }
    }

    private function connectAndBind(ilLDAPServer $server, string $url): mixed
    {
        $connection = $this->withWarningsAsExceptions(static fn() => ldap_connect($url));
        if ($connection === false) {
            throw new RuntimeException('LDAP connection initialization failed.');
        }

        try {
            $this->setRequiredOption($connection, LDAP_OPT_PROTOCOL_VERSION, $server->getVersion());
            $this->setRequiredOption(
                $connection,
                LDAP_OPT_NETWORK_TIMEOUT,
                ilLDAPServer::DEFAULT_NETWORK_TIMEOUT
            );
            $this->setRequiredOption($connection, LDAP_OPT_REFERRALS, $server->isActiveReferrer());

            if ($server->isActiveTLS()) {
                $started = $this->withWarningsAsExceptions(static fn() => ldap_start_tls($connection));
                if (!$started) {
                    throw new RuntimeException('LDAP StartTLS failed.');
                }
            }

            $bind_user = '';
            $bind_password = '';
            if (
                $server->getBindingType() === ilLDAPServer::LDAP_BIND_USER
                && $server->getBindUser() !== ''
            ) {
                $bind_user = $server->getBindUser();
                $bind_password = $server->getBindPassword();
            }
            $bound = $this->withWarningsAsExceptions(
                static fn() => ldap_bind($connection, $bind_user, $bind_password)
            );
            if (!$bound) {
                throw new RuntimeException('LDAP bind failed.');
            }

            return $connection;
        } catch (Throwable $exception) {
            ldap_unbind($connection);
            throw $exception;
        }
    }

    private function setRequiredOption(mixed $connection, int $option, mixed $value): void
    {
        if (!ldap_set_option($connection, $option, $value)) {
            throw new RuntimeException(sprintf('Could not set required LDAP option %d.', $option));
        }
    }

    private function dropConnection(int $server_id): void
    {
        if (!isset($this->connections[$server_id])) {
            return;
        }
        ldap_unbind($this->connections[$server_id]);
        unset($this->connections[$server_id]);
    }

    private function withWarningsAsExceptions(Closure $operation): mixed
    {
        set_error_handler(static function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    public function __destruct()
    {
        foreach (array_keys($this->connections) as $server_id) {
            $this->dropConnection($server_id);
        }
    }
}
