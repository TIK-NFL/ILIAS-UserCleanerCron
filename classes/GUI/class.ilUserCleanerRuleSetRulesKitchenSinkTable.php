<?php
declare(strict_types=1);

use ILIAS\Data\URI;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\URLBuilder;

final class ilUserCleanerRuleSetRulesKitchenSinkTable implements DataRetrieval
{
    public function __construct(
        private readonly ilCtrlInterface $ctrl,
        private readonly ilLanguage $language,
        private readonly UIFactory $uiFactory,
        private readonly HTTPServices $http,
        private readonly string $title,
        private readonly array $labels,
        private readonly array $records
    ) {
    }

    public function getComponent(): ILIAS\UI\Component\Table\Data
    {
        $column = $this->uiFactory->table()->column();
        return $this->uiFactory->table()->data(
            $this->title,
            [
                'sequence' => $column->number($this->labels['sequence'])->withIsSortable(true),
                'rule' => $column->text($this->labels['rule'])->withIsSortable(true),
                'source' => $column->text($this->labels['source'])->withIsSortable(true),
            ],
            $this
        )->withActions($this->getActions());
    }

    private function getActions(): array
    {
        $builder = new URLBuilder(new URI(ILIAS_HTTP_PATH . '/' . $this->ctrl->getLinkTargetByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, ilUserCleanerRulesTableGUI::class],
            ilUserCleanerGUIConstants::CMD_DELETE_RULE_SET_RULES
        )));
        [$builder, $action_token, $ids_token] = $builder->acquireParameters(
            ['rule_set_rules_table'],
            'action',
            'membership_ids'
        );

        return [
            ilUserCleanerGUIConstants::TABLE_ACTION_DELETE => $this->uiFactory->table()->action()->multi(
                $this->language->txt('delete'),
                $builder->withParameter($action_token, ilUserCleanerGUIConstants::TABLE_ACTION_DELETE),
                $ids_token
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
        $records = $this->records;
        [$column_id, $direction] = $order->join([], static fn($ret, $key, $value): array => [$key, $value]);
        usort($records, static function (array $left, array $right) use ($column_id, $direction): int {
            $result = strnatcasecmp((string) ($left[$column_id] ?? ''), (string) ($right[$column_id] ?? ''));
            return strtolower((string) $direction) === 'desc' ? -$result : $result;
        });
        foreach (array_slice($records, $range->getStart(), $range->getLength()) as $record) {
            yield $row_builder->buildDataRow((string) $record['membership_id'], $record);
        }
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return count($this->records);
    }
}
