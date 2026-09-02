<?php
declare(strict_types=1);

enum ilUserCleanerCleanupAction: string
{
    case DEACTIVATE = 'deactivate';
    case DELETE = 'delete';
}
