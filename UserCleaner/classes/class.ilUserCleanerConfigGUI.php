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
 * @author Ulf Bischoff <ulf.bischoff@tik.uni-stuttgart.de>
 * @ilCtrl_isCalledBy ilUserCleanerConfigGUI: ilObjComponentSettingsGUI
 * @ilCtrl_Calls ilUserCleanerConfigGUI: ilUserCleanerAuthGUI, ilUserCleanerExecutionTableGUI, ilUserCleanerExclusionsTableGUI, ilUserCleanerRulesTableGUI
 */


class ilUserCleanerConfigGUI extends ilPluginConfigGUI
{
    private const COMPONENT_PARAMETERS = ['ctype', 'cname', 'slot_id', 'plugin_id', 'pname'];

    /** @var string[] */
    private const SUBTABS = [
        'ilusercleanerauthgui',
        'ilusercleanerrulestablegui',
        'ilusercleanerexecutiontablegui',
        'ilusercleanerexclusionstablegui',
    ];


    public function __construct()
    {
      
    }

    #[Override]
    public function performCommand(string $cmd): void
    {
       
    }

    public function executeCommand(): void
    {
         global $DIC;

        $ctrl = $DIC->ctrl();

        $this->preserveComponentParameters();

        $next_class = $ctrl->getNextClass($this);

        $this->setTabs($next_class);

        switch ($next_class) {
            case strtolower(ilUserCleanerAuthGUI::class):
                $auth_gui = new ilUserCleanerAuthGUI();
                $auth_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($auth_gui);
                break;

            case strtolower(ilUserCleanerExecutionTableGUI::class):
                $execution_gui = new ilUserCleanerExecutionTableGUI();
                $execution_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($execution_gui);
                break;

            case strtolower(ilUserCleanerRulesTableGUI::class):
                $rules_gui = new ilUserCleanerRulesTableGUI();
                $rules_gui->setPluginObject($this->plugin_object);
                $ctrl->forwardCommand($rules_gui);
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
        foreach ([
            self::class,
            ilObjComponentSettingsGUI::class,
            ilUserCleanerAuthGUI::class,
            ilUserCleanerExecutionTableGUI::class,
            ilUserCleanerExclusionsTableGUI::class,
            ilUserCleanerRulesTableGUI::class,
        ] as $class) {
            foreach (self::COMPONENT_PARAMETERS as $parameter) {
                $this->preserveComponentParameter($class, $parameter);
            }
        }
    }

    private function preserveComponentParameter(string $class, string $parameter): void
    {
        global $DIC;

        $query = $DIC->http()->request()->getQueryParams();
        if (!isset($query[$parameter]) || !is_string($query[$parameter])) {
            return;
        }

        $DIC->ctrl()->setParameterByClass(
            $class,
            $parameter,
            ilUtil::stripSlashes($query[$parameter])
        );
    }

    private function setTabs(string $next_class): void
    {
        global $DIC;

        $tabs = $DIC->tabs();
        $ctrl = $DIC->ctrl();
        $sub_tab_details = [
            [self::SUBTABS[0], "subtab_name_general", ilUserCleanerAuthGUI::class],
            [self::SUBTABS[1], "subtab_name_rules", ilUserCleanerRulesTableGUI::class],
            [self::SUBTABS[2], "subtab_name_execution_rules", ilUserCleanerExecutionTableGUI::class],
            [self::SUBTABS[3], "subtab_name_exclusions", ilUserCleanerExclusionsTableGUI::class],
        ];

        foreach ($sub_tab_details as [$id, $lang_key, $sub_tab]) {
            $tabs->addSubTab(
                $id,
                $this->plugin_object->txt($lang_key),
                $ctrl->getLinkTargetByClass([ilObjComponentSettingsGUI::class, self::class, $sub_tab], "show")
            );
        }

        $tabs->activateSubTab(in_array($next_class, self::SUBTABS, true)
            ? $next_class
            : strtolower(ilUserCleanerExclusionsTableGUI::class));
    }
}