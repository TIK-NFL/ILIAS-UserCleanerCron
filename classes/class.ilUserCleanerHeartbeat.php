<?php
declare(strict_types=1);

/** Cooperative heartbeat; cannot run while an ILIAS call is blocked. */
final class ilUserCleanerHeartbeat
{
    private ?float $lastPing = null;

    public function __construct(
        private readonly Closure $ping,
        private readonly Closure $clock
    ) {
    }

    public function __invoke(): void
    {
        $now = ($this->clock)();
        if ($this->lastPing === null || $now - $this->lastPing >= 60.0) {
            ($this->ping)();
            $this->lastPing = $now;
        }
    }
}
