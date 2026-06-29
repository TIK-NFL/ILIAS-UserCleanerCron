<?php
declare(strict_types=1);

use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as RefineryFactory;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

require_once __DIR__ . '/class.ilUserCleanerExecutionKitchenSinkTable.php';

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
 * @ilCtrl_isCalledBy ilUserCleanerExecutionTableGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerExecutionTableGUI:
 */
class ilUserCleanerExecutionTableGUI
{
    private const COMPONENT_PARAMETERS = ['ctype', 'cname', 'slot_id', 'plugin_id', 'pname'];
    private const CMD_SHOW = 'show';
    private const CMD_ADD = 'addExecutionRule';
    private const CMD_HANDLE_TABLE_ACTIONS = 'handleTableActions';
    private const TABLE_ACTION_DELETE = 'delete';
    private const TABLE_ACTION_PARAMETER = 'execution_rules_table_action';
    private const TABLE_ROW_IDS_PARAMETER = 'execution_rules_table_execution_ids';
    private const FORM_FIELD_RULE_ID = 'rule_id';
    private const FORM_FIELD_AUTH_ID = 'auth_id';
    private const FORM_FIELD_ROLE_ID = 'role_id';

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private ilLanguage $lng;
    private ilUserDBConnector $dbConnector;
    private UIFactory $uiFactory;
    private UIRenderer $uiRenderer;
    private HTTPServices $http;
    private RefineryFactory $refinery;
    private ilPlugin $pluginObject;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->dbConnector = new ilUserDBConnector();
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
    }

    public function setPluginObject(ilPlugin $plugin_object): void
    {
        $this->pluginObject = $plugin_object;
    }

    public function executeCommand(): void
    {
        $this->preserveComponentParameters();

        $cmd = $this->ctrl->getCmd(self::CMD_SHOW);
        switch ($cmd) {
            case self::CMD_ADD:
                $this->addExecutionRule();
                return;
            case self::CMD_HANDLE_TABLE_ACTIONS:
                $this->handleTableActions();
                return;
            case self::CMD_SHOW:
            default:
                $this->show();
        }
    }

    private function preserveComponentParameters(): void
    {
        foreach ([
            ilUserCleanerConfigGUI::class,
            self::class,
            ilObjComponentSettingsGUI::class,
        ] as $class) {
            foreach (self::COMPONENT_PARAMETERS as $parameter) {
                $this->preserveComponentParameter($class, $parameter);
            }
        }
    }

    private function preserveComponentParameter(string $class, string $parameter): void
    {
        $query = $this->http->request()->getQueryParams();
        if (!isset($query[$parameter]) || !is_string($query[$parameter])) {
            return;
        }

        $this->ctrl->setParameterByClass(
            $class,
            $parameter,
            ilUtil::stripSlashes($query[$parameter])
        );
    }

    public function show(): void
    {
        $table = $this->getExecutionTable();
        $this->tpl->setContent($this->getExecutionForm()->getHTML() . $this->uiRenderer->render($table->getComponent()));
    }

    private function getExecutionForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObject->txt('execution_rules_form_title'));
        $form->setFormAction($this->ctrl->getFormActionByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            self::CMD_ADD
        ));

        $rule_options = $this->getRuleOptions();
        $rule = new ilSelectInputGUI(
            $this->pluginObject->txt('execution_rules_table_input_rule'),
            self::FORM_FIELD_RULE_ID
        );
        $rule->setOptions($rule_options);
        $rule->setRequired(true);
        if ($rule_options === []) {
            $rule->setInfo($this->pluginObject->txt('execution_rules_form_no_rules'));
        }
        $form->addItem($rule);

        $auth_options = $this->getAuthOptions();
        $auth = new ilSelectInputGUI(
            $this->pluginObject->txt('execution_rules_table_input_auth_mode'),
            self::FORM_FIELD_AUTH_ID
        );
        $auth->setOptions($auth_options);
        $auth->setRequired(true);
        if ($auth_options === []) {
            $auth->setInfo($this->pluginObject->txt('execution_rules_form_no_auth_modes'));
        }
        $form->addItem($auth);

        $role_options = $this->getRoleOptions();
        $role = new ilSelectInputGUI(
            $this->pluginObject->txt('execution_rules_table_input_role'),
            self::FORM_FIELD_ROLE_ID
        );
        $role->setOptions($role_options);
        $role->setRequired(true);
        if ($role_options === []) {
            $role->setInfo($this->pluginObject->txt('execution_rules_form_no_roles'));
        }
        $form->addItem($role);

        $form->addCommandButton(self::CMD_ADD, $this->lng->txt('add'));

        return $form;
    }

    private function addExecutionRule(): void
    {
        $form = $this->getExecutionForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $table = $this->getExecutionTable();
            $this->tpl->setContent($form->getHTML() . $this->uiRenderer->render($table->getComponent()));
            return;
        }

        $rule_id = (int) $form->getInput(self::FORM_FIELD_RULE_ID);
        $auth_id = (int) $form->getInput(self::FORM_FIELD_AUTH_ID);
        $role_id = (int) $form->getInput(self::FORM_FIELD_ROLE_ID);

        if (
            $rule_id > 0
            && $auth_id > 0
            && $role_id > 0
            && !$this->dbConnector->executionRuleExists($rule_id, $auth_id, $role_id)
        ) {
            $this->dbConnector->insertExecutionRule($rule_id, $auth_id, $role_id);
        }

        $this->ctrl->redirect($this, self::CMD_SHOW);
    }

    private function handleTableActions(): void
    {
        $query = $this->http->wrapper()->query();
        if (!$query->has(self::TABLE_ACTION_PARAMETER)) {
            $this->ctrl->redirect($this, self::CMD_SHOW);
            return;
        }

        $action = $query->retrieve(self::TABLE_ACTION_PARAMETER, $this->refinery->to()->string());
        $ids = $query->retrieve(
            self::TABLE_ROW_IDS_PARAMETER,
            $this->refinery->custom()->transformation(static function ($row_ids): array {
                if (is_array($row_ids)) {
                    return $row_ids;
                }

                return strlen((string) $row_ids) > 0 ? explode(',', (string) $row_ids) : [];
            })
        );

        if ($action === self::TABLE_ACTION_DELETE) {
            foreach ($ids as $id) {
                $this->dbConnector->deleteExecutionRule((int) $id);
            }
        }

        $this->ctrl->redirect($this, self::CMD_SHOW);
    }

    private function getExecutionTable(): ilUserCleanerExecutionKitchenSinkTable
    {
        return new ilUserCleanerExecutionKitchenSinkTable(
            $this->dbConnector,
            $this->ctrl,
            $this->lng,
            $this->uiFactory,
            $this->http,
            $this->pluginObject->txt('execution_rules_table_title'),
            [
                'execution_id' => $this->pluginObject->txt('execution_rules_table_column_execution_id'),
                'rule' => $this->pluginObject->txt('execution_rules_table_column_rule'),
                'auth_mode' => $this->pluginObject->txt('execution_rules_table_column_auth_mode'),
                'role' => $this->pluginObject->txt('execution_rules_table_column_role'),
            ],
            $this->getParameterLabels(),
            $this->getAuthLabels(),
            self::CMD_HANDLE_TABLE_ACTIONS
        );
    }

    private function getRuleOptions(): array
    {
        $options = [];
        foreach ($this->dbConnector->getRulesWithParameters() as $rule) {
            $options[(int) $rule['rule_id']] = $this->buildRuleLabel($rule);
        }

        return $options;
    }

    private function getAuthOptions(): array
    {
        $options = [];
        foreach ($this->dbConnector->getAuthModes() as $auth) {
            $auth_mode = (string) $auth['auth_mode'];
            $options[(int) $auth['auth_id']] = $this->getAuthLabel($auth_mode);
        }

        return $options;
    }

    private function getRoleOptions(): array
    {
        $options = [];
        foreach ($this->dbConnector->getGlobalRoles() as $role) {
            $options[(int) $role['rol_id']] = (string) $role['title'];
        }

        return $options;
    }

    private function getParameterLabels(): array
    {
        $labels = [];
        foreach ($this->dbConnector->getParameters() as $parameter) {
            $parameter_key = (string) $parameter['parameter'];
            $labels[$parameter_key] = $this->getParameterLabel($parameter_key);
        }

        return $labels;
    }

    private function getAuthLabels(): array
    {
        $labels = [];
        foreach ($this->dbConnector->getAuthModes() as $auth) {
            $auth_mode = (string) $auth['auth_mode'];
            $labels[$auth_mode] = $this->getAuthLabel($auth_mode);
        }

        return $labels;
    }

    private function buildRuleLabel(array $rule): string
    {
        return sprintf(
            '%s %s %s',
            $this->getParameterLabel((string) $rule['parameter']),
            (string) $rule['symbole'],
            (string) $rule['value']
        );
    }

    private function getParameterLabel(string $parameter_key): string
    {
        return $this->pluginObject->txt('parameter_' . $parameter_key);
    }

    private function getAuthLabel(string $auth_mode): string
    {
        return $this->pluginObject->txt('auth_mode_' . $auth_mode);
    }
}
