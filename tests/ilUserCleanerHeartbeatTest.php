<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ilUserCleanerHeartbeatTest extends TestCase
{
    public function testPingsImmediatelyAndOnlyOncePerMinute(): void
    {
        $time = 100.0;
        $pings = 0;
        $heartbeat = new ilUserCleanerHeartbeat(
            static function () use (&$pings): void { ++$pings; },
            static function () use (&$time): float { return $time; }
        );
        $heartbeat();
        $time = 159.9;
        $heartbeat();
        self::assertSame(1, $pings);
        $time = 160.0;
        $heartbeat();
        $heartbeat();
        self::assertSame(2, $pings);
        $time = 1000.0;
        $heartbeat();
        self::assertSame(3, $pings);
    }

    public function testPingFailureIsNotSilentlyIgnored(): void
    {
        $heartbeat = new ilUserCleanerHeartbeat(
            static function (): void { throw new RuntimeException('Database unavailable'); },
            static fn(): float => 1.0
        );
        $this->expectException(RuntimeException::class);
        $heartbeat();
    }
}
