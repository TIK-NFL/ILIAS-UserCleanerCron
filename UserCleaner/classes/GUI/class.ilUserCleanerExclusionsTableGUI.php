<?php
declare(strict_types=1);

use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\Data\URI;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as RefineryFactory;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\UI\URLBuilder;

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
 * @ilCtrl_isCalledBy ilUserCleanerExclusionsTableGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerExclusionsTableGUI:
 */
class ilUserCleanerExclusionsTableGUI
{
    private const COMPONENT_PARAMETERS = ['ctype', 'cname', 'slot_id', 'plugin_id', 'pname'];
    private const CMD_SHOW = 'show';
    private const CMD_ADD = 'addExclusion';
    private const CMD_AUTOCOMPLETE = 'doUserAutoComplete';
    private const CMD_HANDLE_TABLE_ACTIONS = 'handleTableActions';
    private const TABLE_ACTION_DELETE = 'delete';
    private const TABLE_ACTION_PARAMETER = 'exclusion_table_action';
    private const TABLE_ROW_IDS_PARAMETER = 'exclusion_table_exclusion_ids';
    private const FORM_FIELD_USERS = 'user_logins';

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private ilToolbarGUI $toolbar;
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
        $this->toolbar = $DIC->toolbar();
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
            case self::CMD_AUTOCOMPLETE:
                $this->doUserAutoComplete();
                return;
            case self::CMD_ADD:
                $this->addExclusion();
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
        $this->buildToolbar();

        $table = new ilUserCleanerExclusionsKitchenSinkTable(
            $this->dbConnector,
            $this->ctrl,
            $this->lng,
            $this->uiFactory,
            $this->http,
            $this->pluginObject->txt('exclusion_table_title'),
            [
                'user_id' => $this->pluginObject->txt('exclusion_table_column_user_id'),
                'login' => $this->pluginObject->txt('exclusion_table_column_login'),
                'name' => $this->pluginObject->txt('exclusion_table_column_name'),
                'email' => $this->pluginObject->txt('exclusion_table_column_email'),
            ],
            self::CMD_HANDLE_TABLE_ACTIONS
        );

        $this->tpl->setContent($this->uiRenderer->render($table->getComponent()));
    }

    private function buildToolbar(): void
    {
        $this->toolbar->setFormAction($this->ctrl->getFormActionByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            self::CMD_ADD
        ));

        $user_input = new ilTextInputGUI($this->lng->txt('user'), self::FORM_FIELD_USERS);
        $user_input->setMulti(true, true);
        $user_input->setDataSource($this->ctrl->getLinkTargetByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            self::CMD_AUTOCOMPLETE,
            '',
            true
        ));
        $this->toolbar->addInputItem($user_input, true);

        $this->toolbar->addFormButton($this->lng->txt('add'), self::CMD_ADD, null, true);
    }

    private function addExclusion(): void
    {
        foreach ($this->getSubmittedLogins() as $login) {
            $user_id = ilObjUser::_lookupId($login);
            if ($user_id === null || $this->dbConnector->isUserExcluded((int) $user_id)) {
                continue;
            }

            $this->dbConnector->insertExclusion((int) $user_id);
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
                $this->dbConnector->deleteExclusion((int) $id);
            }
        }

        $this->ctrl->redirect($this, self::CMD_SHOW);
    }

    private function doUserAutoComplete(): void
    {
        $auto = new ilUserAutoComplete();
        $auto->setPrivacyMode(ilUserAutoComplete::PRIVACY_MODE_RESPECT_USER_SETTING);
        $auto->setSearchFields(['login', 'firstname', 'lastname', 'email']);
        $auto->setResultField('login');
        $auto->setMoreLinkAvailable(true);

        $term = $_POST['term'] ?? $_GET['term'] ?? '';
        echo $auto->getList((string) $term);
        exit();
    }

    private function getSubmittedLogins(): array
    {
        $submitted = $_POST[self::FORM_FIELD_USERS] ?? [];
        if (is_string($submitted)) {
            $submitted = preg_split('/[,;\r\n]+/', $submitted) ?: [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn($login): string => trim((string) $login),
            (array) $submitted
        ))));
    }
}

class ilUserCleanerExclusionsKitchenSinkTable implements DataRetrieval
{
    private const ACTION_DELETE = 'delete';

    /** @var list<array<string, scalar>>|null */
    private ?array $records = null;
    public function __construct(
        private ilUserDBConnector $dbConnector,
        private ilCtrlInterface $ctrl,
        private ilLanguage $lng,
        private UIFactory $uiFactory,
        private HTTPServices $http,
        private string $title,
        private array $columnLabels,
        private string $actionCommand
    ) {
    }

    public function getComponent(): ILIAS\UI\Component\Table\Data
    {
        return $this->uiFactory->table()->data(
            $this->title,
            $this->getColumns(),
            $this
        )
            ->withId('exclusion_table')
            ->withOrder(new Order('login', Order::ASC))
            ->withRange(new Range(0, 50))
            ->withActions($this->getActions())
            ->withRequest($this->http->request());
    }

    private function getColumns(): array
    {
        $column = $this->uiFactory->table()->column();

        return [
            'user_id' => $column->number($this->columnLabels['user_id'])->withIsSortable(true),
            'login' => $column->text($this->columnLabels['login'])->withIsSortable(true),
            'name' => $column->text($this->columnLabels['name'])->withIsSortable(true),
            'email' => $column->eMail($this->columnLabels['email'])->withIsSortable(true),
        ];
    }

    private function getActions(): array
    {
        $url_builder = new URLBuilder(
            new URI(ILIAS_HTTP_PATH . '/' . $this->ctrl->getLinkTargetByClass(
                [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, ilUserCleanerExclusionsTableGUI::class],
                $this->actionCommand
            ))
        );
        [$url_builder, $action_token, $row_id_token] = $url_builder->acquireParameters(
            ['exclusion_table'],
            'action',
            'exclusion_ids'
        );

        return [
            self::ACTION_DELETE => $this->uiFactory->table()->action()->multi(
                $this->lng->txt('delete'),
                $url_builder->withParameter($action_token, self::ACTION_DELETE),
                $row_id_token
            ),
        ];
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): Generator {
        foreach ($this->getRecords($range, $order) as $record) {
            yield $row_builder->buildDataRow((string) $record['exclusion_id'], $record);
        }
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        $this->initRecords();

        return count($this->records);
    }

    private function initRecords(): void
    {
        if ($this->records !== null) {
            return;
        }

        $this->records = [];
        foreach ($this->dbConnector->getExclusions() as $row) {
            $user_id = (int) $row['user_id'];
            $name = ilObjUser::_lookupName($user_id);
            $this->records[] = [
                'exclusion_id' => (int) $row['exclusion_id'],
                'user_id' => $user_id,
                'login' => $name['login'],
                'name' => trim($name['firstname'] . ' ' . $name['lastname']),
                'email' => ilObjUser::_lookupEmail($user_id),
            ];
        }
    }

    private function getRecords(Range $range, Order $order): array
    {
        $this->initRecords();
        $records = $this->sortRecords($order);

        return array_slice($records, $range->getStart(), $range->getLength());
    }

    private function sortRecords(Order $order): array
    {
        $records = $this->records;
        [$field, $direction] = $order->join([], static fn($ret, $key, $value): array => [$key, $value]);
        $direction = strtolower((string) $direction);

        usort($records, static function (array $left, array $right) use ($field, $direction): int {
            $result = strnatcasecmp((string) ($left[$field] ?? ''), (string) ($right[$field] ?? ''));

            return $direction === 'desc' ? -$result : $result;
        });

        return $records;
    }
}
