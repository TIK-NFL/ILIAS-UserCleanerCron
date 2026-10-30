<?php
declare(strict_types=1);

use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\UI\Component\Input\Container\Filter\Standard as StandardFilter;
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
    private const FILTER_ID = 'ucc_protocol_filter';

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private UIFactory $uiFactory;
    private UIRenderer $uiRenderer;
    private HTTPServices $http;
    private ilUIService $uiService;
    private ilUserCleanerProtocolRepository $protocol;
    private ilPlugin $pluginObject;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();
        $this->http = $DIC->http();
        $this->uiService = $DIC->uiService();
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
            case ilUserCleanerGUIConstants::CMD_EXPORT_PROTOCOL:
                $this->exportCsv();
                return;
            default:
                $this->show();
        }
    }

    private function show(): void
    {
        $filter = $this->getProtocolFilter();
        $filters = $this->getFilters($filter);
        $table = new ilUserCleanerProtocolKitchenSinkTable(
            $this->uiFactory,
            $this->http,
            $this->pluginObject,
            $this->protocol->getAll($filters)
        );
        $this->tpl->setContent($this->uiRenderer->render([
            $filter,
            $this->uiFactory->button()->standard(
                $this->pluginObject->txt('protocol_export_csv'),
                $this->ctrl->getLinkTargetByClass(
                    [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
                    ilUserCleanerGUIConstants::CMD_EXPORT_PROTOCOL
                )
            ),
            $table->getComponent(),
        ]));
    }

    private function getProtocolFilter(): StandardFilter
    {
        $rule_set_options = ['' => $this->pluginObject->txt('protocol_all_rule_sets')];
        foreach ($this->protocol->getRuleSetTitles() as $title) {
            $rule_set_options[$title] = $title;
        }
        $field = $this->uiFactory->input()->field();
        $inputs = [
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_SEARCH => $field->text(
                $this->pluginObject->txt('protocol_search'),
                $this->pluginObject->txt('protocol_search_info')
            ),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_ACTION => $field->select(
                $this->pluginObject->txt('protocol_column_action'),
                [
                    '' => $this->pluginObject->txt('protocol_all_actions'),
                    'dry_run' => $this->pluginObject->txt('protocol_action_dry_run'),
                    'deactivate' => $this->pluginObject->txt('protocol_action_deactivate'),
                    'delete' => $this->pluginObject->txt('protocol_action_delete'),
                ]
            ),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_FROM => $field->dateTime(
                $this->pluginObject->txt('protocol_date_from')
            ),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_TO => $field->dateTime(
                $this->pluginObject->txt('protocol_date_to')
            ),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_RULE_SET => $field->select(
                $this->pluginObject->txt('protocol_rule_set'),
                $rule_set_options
            ),
        ];

        return $this->uiService->filter()->standard(
            self::FILTER_ID,
            $this->ctrl->getLinkTargetByClass(
                [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
                ilUserCleanerGUIConstants::CMD_SHOW,
                '',
                true
            ),
            $inputs,
            array_fill(0, count($inputs), true),
            true,
            true
        );
    }

    private function exportCsv(): void
    {
        $filters = $this->getFilters($this->getProtocolFilter());
        $csv = new ilCSVWriter();
        $csv->setSeparator(';');
        $columns = [
            'protocol_id', 'created_at', 'user_id', 'matriculation', 'firstname', 'lastname',
            'login', 'external_account', 'email', 'action', 'status', 'rule_sets', 'rules', 'error_message',
        ];
        foreach ($columns as $column) {
            $csv->addColumn($column);
        }
        $csv->addRow();
        foreach ($this->protocol->getAll($filters) as $record) {
            foreach ($columns as $column) {
                $csv->addColumn((string) ($record[$column] ?? ''));
            }
            $csv->addRow();
        }

        $exports = new ilUserCleanerProtocolExportRepository();
        $resource_id = $exports->store(
            "\xEF\xBB\xBF" . $csv->getCSVString(),
            'user-cleaner-protocol-' . date('Y-m-d-His') . '.csv',
            $filters
        );
        $exports->download($resource_id);
    }

    /** @return array{search: string, action: string, date_from: string, date_to: string, rule_set: string} */
    private function getFilters(StandardFilter $filter): array
    {
        $filters = $this->uiService->filter()->getData($filter) ?? [];
        $action = (string) ($filters[ilUserCleanerGUIConstants::FIELD_PROTOCOL_ACTION] ?? '');
        $rule_set = trim((string) ($filters[ilUserCleanerGUIConstants::FIELD_PROTOCOL_RULE_SET] ?? ''));

        return [
            'search' => trim((string) ($filters[ilUserCleanerGUIConstants::FIELD_PROTOCOL_SEARCH] ?? '')),
            'action' => in_array($action, ['', 'dry_run', 'deactivate', 'delete'], true) ? $action : '',
            'date_from' => $this->normalizeDate(
                $filters[ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_FROM] ?? null
            ),
            'date_to' => $this->normalizeDate(
                $filters[ilUserCleanerGUIConstants::FIELD_PROTOCOL_DATE_TO] ?? null
            ),
            'rule_set' => in_array($rule_set, $this->protocol->getRuleSetTitles(), true) ? $rule_set : '',
        ];
    }

    private function normalizeDate(mixed $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        $date = trim((string) $date);
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
                'external_account' => $column->text($this->plugin->txt('protocol_column_external_account'))->withIsSortable(true),
                'email' => $column->text($this->plugin->txt('protocol_column_email'))->withIsSortable(true),
                'action_label' => $column->text($this->plugin->txt('protocol_column_action'))->withIsSortable(true),
                'status_label' => $column->text($this->plugin->txt('protocol_column_status'))->withIsSortable(true),
                'error_message' => $column->text($this->plugin->txt('protocol_column_error'))->withIsSortable(false),
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
            $record['status_label'] = $this->plugin->txt('protocol_status_' . $record['status']);
            $record['error_message'] = (string) ($record['error_message'] ?? '');
            $record['action_label'] = $this->plugin->txt('protocol_action_' . $record['action']);
            return $record;
        }, $this->records);
    }
}
