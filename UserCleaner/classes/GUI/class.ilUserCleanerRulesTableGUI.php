<?php
declare(strict_types=1);

use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as RefineryFactory;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

require_once __DIR__ . '/class.ilUserCleanerRulesKitchenSinkTable.php';

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
 * @ilCtrl_isCalledBy ilUserCleanerRulesTableGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerRulesTableGUI:
 */
class ilUserCleanerRulesTableGUI
{
    private const COMPONENT_PARAMETERS = ['ctype', 'cname', 'slot_id', 'plugin_id', 'pname'];
    private const CMD_SHOW = 'show';
    private const CMD_ADD = 'addRule';
    private const CMD_HANDLE_TABLE_ACTIONS = 'handleTableActions';
    private const TABLE_ACTION_DELETE = 'delete';
    private const TABLE_ACTION_PARAMETER = 'rules_table_action';
    private const TABLE_ROW_IDS_PARAMETER = 'rules_table_rule_ids';
    private const FORM_FIELD_PARAMETER_ID = 'parameter_id';
    private const FORM_FIELD_SYMBOL = 'symbol';
    private const FORM_FIELD_VALUE = 'value';

    private const SYMBOLS = [
        '=' => '=',
        '!=' => '!=',
        '<' => '<',
        '<=' => '<=',
        '>' => '>',
        '>=' => '>=',
    ];

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
                $this->addRule();
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
        $table = $this->getRulesTable();
        $this->tpl->setContent($this->getRuleForm()->getHTML() . $this->uiRenderer->render($table->getComponent()));
    }

    private function getRuleForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObject->txt('rules_form_title'));
        $form->setFormAction($this->ctrl->getFormActionByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            self::CMD_ADD
        ));

        $parameter_options = $this->getParameterOptions();
        $parameter = new ilSelectInputGUI(
            $this->pluginObject->txt('rules_table_input_parameter'),
            self::FORM_FIELD_PARAMETER_ID
        );
        $parameter->setOptions($parameter_options);
        $parameter->setRequired(true);
        if ($parameter_options === []) {
            $parameter->setInfo($this->pluginObject->txt('rules_form_no_parameters'));
        }
        $form->addItem($parameter);

        $symbol = new ilSelectInputGUI(
            $this->pluginObject->txt('rules_table_input_symbol'),
            self::FORM_FIELD_SYMBOL
        );
        $symbol->setOptions(self::SYMBOLS);
        $symbol->setRequired(true);
        $form->addItem($symbol);

        $value = new ilNumberInputGUI(
            $this->pluginObject->txt('rules_table_input_value'),
            self::FORM_FIELD_VALUE
        );
        $value->setDecimals(0);
        $value->setSize(8);
        $value->setRequired(true);
        $form->addItem($value);

        $form->addCommandButton(self::CMD_ADD, $this->lng->txt('add'));

        return $form;
    }

    private function addRule(): void
    {
        $form = $this->getRuleForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $table = $this->getRulesTable();
            $this->tpl->setContent($form->getHTML() . $this->uiRenderer->render($table->getComponent()));
            return;
        }

        $parameter_id = (int) $form->getInput(self::FORM_FIELD_PARAMETER_ID);
        $symbol = (string) $form->getInput(self::FORM_FIELD_SYMBOL);
        $value = (int) $form->getInput(self::FORM_FIELD_VALUE);

        if ($parameter_id > 0 && isset(self::SYMBOLS[$symbol])) {
            $this->dbConnector->insertRule($parameter_id, $symbol, $value);
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
                $this->dbConnector->deleteRule((int) $id);
            }
        }

        $this->ctrl->redirect($this, self::CMD_SHOW);
    }

    private function getRulesTable(): ilUserCleanerRulesKitchenSinkTable
    {
        return new ilUserCleanerRulesKitchenSinkTable(
            $this->dbConnector,
            $this->ctrl,
            $this->lng,
            $this->uiFactory,
            $this->http,
            $this->pluginObject->txt('rules_table_title'),
            [
                'rule_id' => $this->pluginObject->txt('rules_table_column_rule_id'),
                'parameter' => $this->pluginObject->txt('rules_table_column_parameter'),
                'symbole' => $this->pluginObject->txt('rules_table_column_symbol'),
                'value' => $this->pluginObject->txt('rules_table_column_value'),
            ],
            $this->getParameterLabels(),
            self::CMD_HANDLE_TABLE_ACTIONS
        );
    }

    private function getParameterOptions(): array
    {
        $options = [];
        foreach ($this->dbConnector->getParameters() as $parameter) {
            $parameter_key = (string) $parameter['parameter'];
            $options[(int) $parameter['parameter_id']] = $this->getParameterLabel($parameter_key);
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

    private function getParameterLabel(string $parameter_key): string
    {
        return $this->pluginObject->txt('parameter_' . $parameter_key);
    }
}
