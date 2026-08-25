<?php
declare(strict_types=1);

use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Factory as UIFactory;

final class ilUserCleanerRuleSetsKitchenSinkTable implements DataRetrieval
{
    public function __construct(
        private readonly UIFactory $uiFactory,
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
                'title' => $column->text($this->labels['title'])->withIsSortable(true),
                'role' => $column->text($this->labels['role'])->withIsSortable(true),
                'auth_mode' => $column->text($this->labels['auth_mode'])->withIsSortable(true),
                'rules' => $column->number($this->labels['rules'])->withIsSortable(true),
                'condition' => $column->text($this->labels['condition'])->withIsSortable(false),
                'sources' => $column->text($this->labels['sources'])->withIsSortable(true),
                'enabled' => $column->text($this->labels['enabled'])->withIsSortable(true),
                'actions' => $column->linkListing($this->labels['actions']),
            ],
            $this
        );
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
            yield $row_builder->buildDataRow((string) $record['rule_set_id'], $record);
        }
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return count($this->records);
    }
}
