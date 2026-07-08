<?php
declare(strict_types=1);

use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\Data\URI;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Factory as UIFactory;
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


class ilUserCleanerRulesKitchenSinkTable implements DataRetrieval
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
        private array $parameterLabels,
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
            ->withId('rules_table')
            ->withOrder(new Order('parameter', Order::ASC))
            ->withRange(new Range(0, 50))
            ->withActions($this->getActions())
            ->withRequest($this->http->request());
    }

    private function getColumns(): array
    {
        $column = $this->uiFactory->table()->column();

        return [
            'rule_id' => $column->number($this->columnLabels['rule_id'])->withIsSortable(true),
            'parameter' => $column->text($this->columnLabels['parameter'])->withIsSortable(true),
            'symbole' => $column->text($this->columnLabels['symbole'])->withIsSortable(true),
            'value' => $column->number($this->columnLabels['value'])->withIsSortable(true),
        ];
    }

    private function getActions(): array
    {
        $url_builder = new URLBuilder(
            new URI(ILIAS_HTTP_PATH . '/' . $this->ctrl->getLinkTargetByClass(
                [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, ilUserCleanerRulesTableGUI::class],
                $this->actionCommand
            ))
        );
        [$url_builder, $action_token, $row_id_token] = $url_builder->acquireParameters(
            ['rules_table'],
            'action',
            'rule_ids'
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
            yield $row_builder->buildDataRow((string) $record['rule_id'], $record);
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
        foreach ($this->dbConnector->getRulesWithParameters() as $row) {
            $this->records[] = [
                'rule_id' => (int) $row['rule_id'],
                'parameter_id' => (int) $row['parameter_id'],
                'parameter' => $this->parameterLabels[(string) $row['parameter']] ?? (string) $row['parameter'],
                'symbole' => (string) $row['symbole'],
                'value' => (int) $row['value'],
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
