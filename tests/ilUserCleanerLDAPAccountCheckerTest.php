<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerLDAPAccountCheckerTest extends TestCase
{
    public function testSuccessfulEmptySearchIsTheOnlyMissingAccountResult(): void
    {
        $directory = new ilUserCleanerTestLDAPDirectory(false);
        $checker = $this->checker($directory);

        self::assertFalse($checker->accountExists('ldap:7', 'user*)(uid=*)'));
        self::assertSame('ou=People,dc=Example,dc=ORG', $directory->searchBase);
        self::assertSame('(&(uid=user\\2a\\29\\28uid=\\2a\\29)(objectClass=person))', $directory->filter);
        self::assertSame(['uid'], $directory->attributes);
    }

    public function testSuccessfulFoundSearchReturnsTrue(): void
    {
        self::assertTrue($this->checker(new ilUserCleanerTestLDAPDirectory(true))
            ->accountExists('ldap:7', 'existing-user'));
    }

    public function testSearchErrorNeverClassifiesAccountAsMissing(): void
    {
        $directory = new ilUserCleanerTestLDAPDirectory(false, true);
        $checker = $this->checker($directory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('the account was not classified as missing');
        $checker->accountExists('ldap:7', 'unknown-user');
    }

    public function testAvailabilityErrorAbortsConfigurationCheck(): void
    {
        $directory = new ilUserCleanerTestLDAPDirectory(false, false, true);

        $this->expectException(RuntimeException::class);
        $this->checker($directory)->assertConfigurationAvailable('ldap:7');
    }

    public function testInactiveSourceIsRejectedBeforeConnecting(): void
    {
        $directory = new ilUserCleanerTestLDAPDirectory(false);
        $checker = $this->checker($directory, false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or inactive');
        try {
            $checker->assertConfigurationAvailable('ldap:7');
        } finally {
            self::assertSame(0, $directory->availabilityChecks);
        }
    }

    public function testMalformedSourceIdentifierIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid LDAP source configuration');
        $this->checker(new ilUserCleanerTestLDAPDirectory(false))
            ->accountExists('ldap:7 OR 1=1', 'user');
    }

    private function checker(
        ilUserCleanerLDAPDirectory $directory,
        bool $active = true
    ): ilUserCleanerLDAPAccountChecker {
        $statement = $this->createMock(ilDBStatement::class);
        $database = $this->createMock(ilDBInterface::class);
        $database->method('tableExists')->with('ldap_server_settings')->willReturn(true);
        $database->method('queryF')->willReturn($statement);
        $database->method('fetchAssoc')->with($statement)->willReturn([
            'server_id' => 7,
            'name' => 'Directory',
            'active' => $active ? 1 : 0,
            'authentication' => 1,
            'authentication_type' => 0,
        ]);

        $server = $this->createMock(ilLDAPServer::class);
        $server->method('getServerId')->willReturn(7);
        $server->method('getSearchBase')->willReturn('ou=People');
        $server->method('getBaseDN')->willReturn('dc=Example,dc=ORG');
        $server->method('getUserAttribute')->willReturn('uid');
        $server->method('getFilter')->willReturn('(objectClass=person)');

        return new ilUserCleanerLDAPAccountChecker(
            new ilUserCleanerLDAPSourceRepository($database),
            $directory,
            static fn(int $server_id): ilLDAPServer => $server
        );
    }
}

final class ilUserCleanerTestLDAPDirectory implements ilUserCleanerLDAPDirectory
{
    public int $availabilityChecks = 0;
    public ?string $searchBase = null;
    public ?string $filter = null;
    public array $attributes = [];

    public function __construct(
        private readonly bool $found,
        private readonly bool $searchFails = false,
        private readonly bool $availabilityFails = false
    ) {
    }

    public function assertAvailable(ilLDAPServer $server): void
    {
        ++$this->availabilityChecks;
        if ($this->availabilityFails) {
            throw new RuntimeException('LDAP bind failed.');
        }
    }

    public function hasEntry(
        ilLDAPServer $server,
        string $search_base,
        string $filter,
        array $attributes
    ): bool {
        $this->searchBase = $search_base;
        $this->filter = $filter;
        $this->attributes = $attributes;
        if ($this->searchFails) {
            throw new RuntimeException('LDAP search failed.');
        }
        return $this->found;
    }
}
