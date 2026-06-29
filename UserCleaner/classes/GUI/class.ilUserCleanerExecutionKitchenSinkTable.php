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

class ilUserCleanerExecutionKitchenSinkTable implements DataRetrieval
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
        private array $authLabels,
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
            ->withId('execution_rules_table')
            ->withOrder(new Order('rule', Order::ASC))
            ->withRange(new Range(0, 50))
            ->withActions($this->getActions())
            ->withRequest($this->http->request());
    }

    private function getColumns(): array
    {
        $column = $this->uiFactory->table()->column();

        return [
            'execution_id' => $column->number($this->columnLabels['execution_id'])->withIsSortable(true),
            'rule' => $column->text($this->columnLabels['rule'])->withIsSortable(true),
            'auth_mode' => $column->text($this->columnLabels['auth_mode'])->withIsSortable(true),
            'role' => $column->text($this->columnLabels['role'])->withIsSortable(true),
        ];
    }

    private function getActions(): array
    {
        $url_builder = new URLBuilder(
            new URI(ILIAS_HTTP_PATH . '/' . $this->ctrl->getLinkTargetByClass(
                [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, ilUserCleanerExecutionTableGUI::class],
                $this->actionCommand
            ))
        );
        [$url_builder, $action_token, $row_id_token] = $url_builder->acquireParameters(
            ['execution_rules_table'],
            'action',
            'execution_ids'
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
            yield $row_builder->buildDataRow((string) $record['execution_id'], $record);
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
        foreach ($this->dbConnector->getExecutionRulesWithDetails() as $row) {
            $this->records[] = [
                'execution_id' => (int) $row['execution_id'],
                'rule_id' => (int) $row['rule_id'],
                'auth_id' => (int) $row['auth_id'],
                'role_id' => (int) $row['role_id'],
                'rule' => $this->buildRuleLabel($row),
                'auth_mode' => $this->authLabels[(string) $row['auth_mode']] ?? (string) $row['auth_mode'],
                'role' => (string) ($row['role_title'] ?? $row['role_id']),
            ];
        }
    }

    private function buildRuleLabel(array $row): string
    {
        return sprintf(
            '%s %s %s',
            $this->parameterLabels[(string) $row['parameter']] ?? (string) $row['parameter'],
            (string) $row['symbole'],
            (string) $row['value']
        );
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
