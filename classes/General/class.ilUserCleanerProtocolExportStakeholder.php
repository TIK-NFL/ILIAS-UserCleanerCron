<?php
declare(strict_types=1);

use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

final class ilUserCleanerProtocolExportStakeholder extends AbstractResourceStakeholder
{
    public function getConsumerNameForPresentation(): string
    {
        return 'UserCleaner';
    }

    public function getId(): string
    {
        return 'user_cleaner_protocol';
    }

    public function getOwnerOfNewResources(): int
    {
        return $this->default_owner;
    }

    public function isResourceInUse(ResourceIdentification $identification): bool
    {
        global $DIC;
        if (!isset($DIC)) {
            return false;
        }
        $result = $DIC->database()->queryF(
            'SELECT export_id FROM ucc_protocol_export WHERE resource_id = %s',
            ['text'],
            [$identification->serialize()]
        );
        return (bool) $DIC->database()->fetchAssoc($result);
    }

    public function resourceHasBeenDeleted(ResourceIdentification $identification): bool
    {
        global $DIC;
        if (isset($DIC)) {
            $DIC->database()->manipulateF(
                'DELETE FROM ucc_protocol_export WHERE resource_id = %s',
                ['text'],
                [$identification->serialize()]
            );
        }
        return true;
    }
}
