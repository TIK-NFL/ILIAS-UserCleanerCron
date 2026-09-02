<?php
declare(strict_types=1);

final class ilUserCleanerActionResult
{
    public function __construct(
        public readonly int $changed,
        public readonly int $unchanged,
        public readonly int $failed
    ) {
    }
}
