<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerNativeLDAPDirectoryTest extends TestCase
{
    public function testUnreachableEndpointFailsAvailabilityCheck(): void
    {
        if (!extension_loaded('ldap')) {
            self::markTestSkipped('The LDAP extension is required.');
        }

        $server = $this->createMock(ilLDAPServer::class);
        $server->method('getServerId')->willReturn(77);
        $server->method('getUrl')->willReturn('ldap://127.0.0.1:1');
        $server->method('getVersion')->willReturn(3);
        $server->method('isActiveReferrer')->willReturn(false);
        $server->method('isActiveTLS')->willReturn(false);
        $server->method('getBindingType')->willReturn(ilLDAPServer::LDAP_BIND_ANONYMOUS);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no fallback was attempted');
        (new ilUserCleanerNativeLDAPDirectory())->assertAvailable($server);
    }

    public function testFallbackListIsIgnored(): void
    {
        if (!extension_loaded('ldap')) {
            self::markTestSkipped('The LDAP extension is required.');
        }

        $server = $this->createMock(ilLDAPServer::class);
        $server->expects(self::never())->method('getUrlString');
        $server->method('getServerId')->willReturn(77);
        $server->method('getUrl')->willReturn('ldap://127.0.0.1:1');
        $server->method('getVersion')->willReturn(3);
        $server->method('isActiveReferrer')->willReturn(false);
        $server->method('isActiveTLS')->willReturn(false);
        $server->method('getBindingType')->willReturn(ilLDAPServer::LDAP_BIND_ANONYMOUS);

        try {
            (new ilUserCleanerNativeLDAPDirectory())->assertAvailable($server);
            self::fail('The unavailable current endpoint must abort the check.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('no fallback was attempted', $exception->getMessage());
        }
    }

    public function testSearchTransportFailureIsNotAnEmptyResult(): void
    {
        if (!extension_loaded('ldap')) {
            self::markTestSkipped('The LDAP extension is required.');
        }

        $connection = ldap_connect('ldap://127.0.0.1:1');
        self::assertNotFalse($connection);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 1);

        $directory = new ilUserCleanerNativeLDAPDirectory();
        $connections = new ReflectionProperty($directory, 'connections');
        $connections->setValue($directory, [77 => $connection]);

        $server = $this->createMock(ilLDAPServer::class);
        $server->method('getServerId')->willReturn(77);
        $server->method('getUserScope')->willReturn(ilLDAPServer::LDAP_SCOPE_SUB);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('account absence was not confirmed');
        $directory->hasEntry(
            $server,
            'dc=example,dc=invalid',
            '(uid=nonexistent-test)',
            ['uid']
        );
    }
}
