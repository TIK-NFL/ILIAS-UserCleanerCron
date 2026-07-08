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

use ILIAS\Cron\Schedule\CronJobScheduleType;

class ilUserCleanerJob extends ilCronJob
{
    public function getId(): string
    {
        return "ucc";
    }

    public function getTitle(): string
    {
        return "User Cleaner";
    }

    public function getDescription(): string
    {
        return "Should clean up old unused users";
    }

    public function hasAutoActivation(): bool
    {
        return false;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function getCustomSettingsGUIClass(): string
    {
        return ilUserCleanerConfigGUI::class;
    }

    public function getDefaultScheduleType(): CronJobScheduleType
    {
        return CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS;
    }

    public function getDefaultScheduleValue(): ?int
    {
        return 1;
    }

    public function hasCustomSettings(): bool
    {
        return true;
    }

    public function run(): ilCronJobResult
    {
        $result = new ilCronJobResult();

        try {
            // your task here

            $result->setStatus(ilCronJobResult::STATUS_OK);
        } catch (Throwable $e) {
            $result->setStatus(ilCronJobResult::STATUS_FAIL);
            $result->setMessage($e->getMessage());
        }

        return $result;
    }
}