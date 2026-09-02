<?php
declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ********************************************************************
 */

class ilUserCleanerPlugin extends ilCronHookPlugin
{
    private const DATABASE_TABLES = [
        'ucc_rule_set_rule',
        'ucc_execution_rules',
        'ucc_rule',
        'ucc_rule_set',
        'ucc_exclusion',
        'ucc_protocol_export',
        'ucc_protocol',
        'ucc_parameter',
        'ucc_auth',
        'ucc_config',
    ];

    public function getPluginName(): string
    {
        return "UserCleaner";
    }

    public function getCronJobInstances(): array
    {
        return [
            new ilUserCleanerJob()
        ];
    }
    public function hasConfigureClass(): bool
    {
        return true;
    }

    public function getConfigureClassName(): string
    {
        return "ilUserCleanerConfigGUI";
    }

    public function getCronJobInstance(string $jobId): ilCronJob
    {
        foreach ($this->getCronJobInstances() as $job) {
            if ($job->getId() === $jobId) {
                return $job;
            }
        }

        throw new OutOfBoundsException("Unknown cron job: " . $jobId);
    }

    protected function beforeUninstall(): bool
    {
        global $DIC;

        $database = $DIC->database();
        (new ilUserCleanerProtocolExportRepository())->deleteAll();

        if ($database->tableExists('cron_job')) {
            $database->manipulateF(
                'DELETE FROM cron_job WHERE job_id = %s',
                ['text'],
                ['ucc']
            );
        }

        foreach (self::DATABASE_TABLES as $table) {
            if ($database->tableExists($table)) {
                $database->dropTable($table);
            }
            if ($database->sequenceExists($table)) {
                $database->dropSequence($table);
            }
        }

        return true;
    }
}
