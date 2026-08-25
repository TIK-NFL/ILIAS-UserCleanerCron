<?php
declare(strict_types=1);

use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

/**
 * @ilCtrl_isCalledBy ilUserCleanerProtocolGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerProtocolGUI:
 */
final class ilUserCleanerProtocolGUI
{
    private const SESSION_FILTERS = 'ucc_protocol_filters';

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private ilToolbarGUI $toolbar;
    private ilLanguage $lng;
    private UIFactory $uiFactory;
    private UIRenderer $uiRenderer;
    private HTTPServices $http;
    private ilUserCleanerProtocolRepository $protocol;
    private ilPlugin $pluginObject;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();
        $this->lng = $DIC->language();
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();
        $this->http = $DIC->http();
        $this->protocol = new ilUserCleanerProtocolRepository();
    }

    public function setPluginObject(ilPlugin $plugin_object): void
    {
        $this->pluginObject = $plugin_object;
    }

    public function executeCommand(): void
    {
        ilUserCleanerGUIHelper::preserveComponentParameters([
            ilUserCleanerConfigGUI::class,
            self::class,
            ilObjComponentSettingsGUI::class,
        ]);

        switch ($this->ctrl->getCmd(ilUserCleanerGUIConstants::CMD_SHOW)) {
            case ilUserCleanerGUIConstants::CMD_APPLY_PROTOCOL_FILTER:
                $this->applyFilter();
                return;
            case ilUserCleanerGUIConstants::CMD_RESET_PROTOCOL_FILTER:
                ilSession::set(self::SESSION_FILTERS, []);
                $this->ctrl->redirect($this, ilUserCleanerGUIConstants::CMD_SHOW);
                return;
            case ilUserCleanerGUIConstants::CMD_EXPORT_PROTOCOL:
                $this->exportCsv();
                return;
            default:
                $this->show();
        }
    }

    private function show(): void
    {
        $filters = $this->getFilters();
        $this->buildToolbar($filters);
        $table = new ilUserCleanerProtocolKitchenSinkTable(
            $this->uiFactory,
            $this->http,
            $this->pluginObject,
            $this->protocol->getAll($filters)
        );
        $this->tpl->setContent($this->uiRenderer->render($table->getComponent()));
    }

    /** @param array{search: string, action: string, date_from: string, date_to: string, rule_set: string} $filters */
    private function buildToolbar(array $filters): void
    {
        $this->toolbar->setFormAction($this->ctrl->getFormActionByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            ilUserCleanerGUIConstants::CMD_APPLY_PROTOCOL_FILTER
        ));

        $search = new ilTextInputGUI(
            $this->pluginObject->txt('protocol_search'),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_SEARCH
        );
        $search->setInfo($this->pluginObject->txt('protocol_search_info'));
        $search->setValue($filters['search']);
        $this->toolbar->addInputItem($search, true);

        $action = new ilSelectInputGUI('', ilUserCleanerGUIConstants::FIELD_PROTOCOL_ACTION);
        $action->setOptions([
            '' => $this->pluginObject->txt('protocol_all_actions'),
            'dry_run' => $this->pluginObject->txt('protocol_action_dry_run'),
            'deactivate' => $this->pluginObject->txt('protocol_action_deactivate'),
            'delete' => $this->pluginObject->txt('protocol_action_delete'),
        ]);
        $action->setValue($filters['action']);
        $this->toolbar->addInputItem($action, true);

        $date_from = new ilTextInputGUI(
            $this->pluginObject->txt('protocol_date_from'),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_FROM
        );
        $date_from->setValue($filters['date_from']);
        $date_from->setMaxLength(10);
        $this->toolbar->addInputItem($date_from, true);

        $date_to = new ilTextInputGUI(
            $this->pluginObject->txt('protocol_date_to'),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_TO
        );
        $date_to->setValue($filters['date_to']);
        $date_to->setMaxLength(10);
        $this->toolbar->addInputItem($date_to, true);

        $rule_set_options = ['' => $this->pluginObject->txt('protocol_all_rule_sets')];
        foreach ($this->protocol->getRuleSetTitles() as $title) {
            $rule_set_options[$title] = $title;
        }
        $rule_set = new ilSelectInputGUI(
            $this->pluginObject->txt('protocol_rule_set'),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_RULE_SET
        );
        $rule_set->setOptions($rule_set_options);
        $rule_set->setValue($filters['rule_set']);
        $this->toolbar->addInputItem($rule_set, true);

        $this->toolbar->addFormButton(
            $this->pluginObject->txt('protocol_apply_filter'),
            ilUserCleanerGUIConstants::CMD_APPLY_PROTOCOL_FILTER
        );
        $this->toolbar->addFormButton(
            $this->pluginObject->txt('protocol_reset_filter'),
            ilUserCleanerGUIConstants::CMD_RESET_PROTOCOL_FILTER
        );
        $this->toolbar->addFormButton(
            $this->pluginObject->txt('protocol_export_csv'),
            ilUserCleanerGUIConstants::CMD_EXPORT_PROTOCOL
        );
    }

    private function applyFilter(): void
    {
        $action = (string) ($_POST[ilUserCleanerGUIConstants::FIELD_PROTOCOL_ACTION] ?? '');
        $date_from = $this->validatedDate(
            (string) ($_POST[ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_FROM] ?? '')
        );
        $date_to = $this->validatedDate(
            (string) ($_POST[ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_TO] ?? '')
        );
        $rule_set = trim((string) ($_POST[ilUserCleanerGUIConstants::FIELD_PROTOCOL_RULE_SET] ?? ''));
        ilSession::set(self::SESSION_FILTERS, [
            'search' => trim((string) ($_POST[ilUserCleanerGUIConstants::FIELD_PROTOCOL_SEARCH] ?? '')),
            'action' => in_array($action, ['', 'dry_run', 'deactivate', 'delete'], true) ? $action : '',
            'date_from' => $date_from,
            'date_to' => $date_to,
            'rule_set' => in_array($rule_set, $this->protocol->getRuleSetTitles(), true) ? $rule_set : '',
        ]);
        $this->ctrl->redirect($this, ilUserCleanerGUIConstants::CMD_SHOW);
    }

    private function exportCsv(): void
    {
        $csv = new ilCSVWriter();
        $csv->setSeparator(';');
        $columns = [
            'protocol_id', 'created_at', 'user_id', 'matriculation', 'firstname', 'lastname',
            'login', 'external_account', 'email', 'action', 'rule_sets', 'rules', 'error_message',
        ];
        foreach ($columns as $column) {
            $csv->addColumn($column);
        }
        $csv->addRow();
        foreach ($this->protocol->getAll($this->getFilters()) as $record) {
            foreach ($columns as $column) {
                $csv->addColumn((string) ($record[$column] ?? ''));
            }
            $csv->addRow();
        }

        $exports = new ilUserCleanerProtocolExportRepository();
        $resource_id = $exports->store(
            "\xEF\xBB\xBF" . $csv->getCSVString(),
            'user-cleaner-protocol-' . date('Y-m-d-His') . '.csv',
            $this->getFilters()
        );
        $exports->download($resource_id);
    }

    /** @return array{search: string, action: string, date_from: string, date_to: string, rule_set: string} */
    private function getFilters(): array
    {
        $filters = ilSession::get(self::SESSION_FILTERS);
        if (!is_array($filters)) {
            $filters = [];
        }

        return [
            'search' => (string) ($filters['search'] ?? ''),
            'action' => (string) ($filters['action'] ?? ''),
            'date_from' => (string) ($filters['date_from'] ?? ''),
            'date_to' => (string) ($filters['date_to'] ?? ''),
            'rule_set' => (string) ($filters['rule_set'] ?? ''),
        ];
    }

    private function validatedDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $date : '';
    }
}

final class ilUserCleanerProtocolKitchenSinkTable implements DataRetrieval
{
    public function __construct(
        private readonly UIFactory $uiFactory,
        private readonly HTTPServices $http,
        private readonly ilPlugin $plugin,
        private readonly array $records
    ) {
    }

    public function getComponent(): ILIAS\UI\Component\Table\Data
    {
        $column = $this->uiFactory->table()->column();
        return $this->uiFactory->table()->data(
            $this->plugin->txt('protocol_title'),
            [
                'created_at' => $column->text($this->plugin->txt('protocol_column_time'))->withIsSortable(true),
                'matriculation' => $column->text($this->plugin->txt('protocol_column_matriculation'))->withIsSortable(true),
                'name' => $column->text($this->plugin->txt('protocol_column_name'))->withIsSortable(true),
                'login' => $column->text($this->plugin->txt('protocol_column_login'))->withIsSortable(true),
                'email' => $column->text($this->plugin->txt('protocol_column_email'))->withIsSortable(true),
                'action_label' => $column->text($this->plugin->txt('protocol_column_action'))->withIsSortable(true),
                'rule_sets' => $column->text($this->plugin->txt('protocol_column_rule_sets'))->withIsSortable(true),
                'rules' => $column->text($this->plugin->txt('protocol_column_rules'))->withIsSortable(false),
            ],
            $this
        )
            ->withId('user_cleaner_protocol')
            ->withOrder(new Order('created_at', Order::DESC))
            ->withRange(new Range(0, 50))
            ->withRequest($this->http->request());
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): Generator {
        $records = $this->prepareRecords();
        [$field, $direction] = $order->join([], static fn($ret, $key, $value): array => [$key, $value]);
        usort($records, static function (array $left, array $right) use ($field, $direction): int {
            $result = strnatcasecmp((string) ($left[$field] ?? ''), (string) ($right[$field] ?? ''));
            return strtolower((string) $direction) === 'desc' ? -$result : $result;
        });
        foreach (array_slice($records, $range->getStart(), $range->getLength()) as $record) {
            yield $row_builder->buildDataRow((string) $record['protocol_id'], $record);
        }
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return count($this->records);
    }

    /** @return array<int, array<string, mixed>> */
    private function prepareRecords(): array
    {
        return array_map(function (array $record): array {
            $record['name'] = trim($record['firstname'] . ' ' . $record['lastname']);
            $record['action_label'] = $this->plugin->txt('protocol_action_' . $record['action']);
            return $record;
        }, $this->records);
    }
}
