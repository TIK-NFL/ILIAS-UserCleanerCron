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

/**
 * @ilCtrl_isCalledBy ilUserCleanerConfigGUI: ilObjComponentSettingsGUI
 * @ilCtrl_Calls ilUserCleanerConfigGUI: ilUserCleanerSettingsGUI, ilUserCleanerAuthGUI, ilUserCleanerExclusionsTableGUI, ilUserCleanerRulesTableGUI, ilUserCleanerProtocolGUI
 */


class ilUserCleanerConfigGUI extends ilPluginConfigGUI
{
    /** @var string[] */
    private const SUBTABS = [
        'ilusercleanersettingsgui',
        'ilusercleanerauthgui',
        'ilusercleanerrulestablegui',
        'ilusercleanerexclusionstablegui',
        'ilusercleanerprotocolgui',
    ];
    #[Override]
    public function performCommand(string $cmd): void
    {
        global $DIC;

        $ctrl = $DIC->ctrl();

        $this->preserveComponentParameters();

        $next_class = $ctrl->getNextClass($this);
        $this->setTabs($next_class);

        switch ($next_class) {
            case strtolower(ilUserCleanerSettingsGUI::class):
                $settings_gui = new ilUserCleanerSettingsGUI();
                $settings_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($settings_gui);
                break;

            case strtolower(ilUserCleanerAuthGUI::class):
                $auth_gui = new ilUserCleanerAuthGUI();
                $auth_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($auth_gui);
                break;

            case strtolower(ilUserCleanerRulesTableGUI::class):
                $rules_gui = new ilUserCleanerRulesTableGUI();
                $rules_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($rules_gui);
                break;

            case strtolower(ilUserCleanerProtocolGUI::class):
                $protocol_gui = new ilUserCleanerProtocolGUI();
                $protocol_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($protocol_gui);
                break;

            default:
            case strtolower(ilUserCleanerExclusionsTableGUI::class):
                $exclusions_gui = new ilUserCleanerExclusionsTableGUI();
                $exclusions_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($exclusions_gui);
                break;
        }
    }
    private function preserveComponentParameters(): void
    {
        ilUserCleanerGUIHelper::preserveComponentParameters([
            self::class,
            ilObjComponentSettingsGUI::class,
            ilUserCleanerSettingsGUI::class,
            ilUserCleanerAuthGUI::class,
            ilUserCleanerExclusionsTableGUI::class,
            ilUserCleanerRulesTableGUI::class,
            ilUserCleanerProtocolGUI::class,
        ]);
    }

    private function setTabs(string $next_class): void
    {
        global $DIC;

        $tabs = $DIC->tabs();
        $ctrl = $DIC->ctrl();
        $sub_tab_details = [
            [self::SUBTABS[0], "subtab_name_settings", ilUserCleanerSettingsGUI::class],
            [self::SUBTABS[1], "subtab_name_general", ilUserCleanerAuthGUI::class],
            [self::SUBTABS[2], "subtab_name_rule_sets", ilUserCleanerRulesTableGUI::class],
            [self::SUBTABS[3], "subtab_name_exclusions", ilUserCleanerExclusionsTableGUI::class],
            [self::SUBTABS[4], "subtab_name_protocol", ilUserCleanerProtocolGUI::class],
        ];

        foreach ($sub_tab_details as [$id, $lang_key, $sub_tab]) {
            $tabs->addSubTab(
                $id,
                $this->plugin_object->txt($lang_key),
                $ctrl->getLinkTargetByClass(
                    [ilObjComponentSettingsGUI::class, self::class, $sub_tab],
                    ilUserCleanerGUIConstants::CMD_SHOW
                )
            );
        }

        $tabs->activateSubTab(in_array($next_class, self::SUBTABS, true)
            ? $next_class
            : strtolower(ilUserCleanerExclusionsTableGUI::class));
    }
}
