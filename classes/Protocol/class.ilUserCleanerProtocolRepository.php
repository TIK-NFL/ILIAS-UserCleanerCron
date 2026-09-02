<?php
declare(strict_types=1);

final class ilUserCleanerProtocolRepository extends ilUserCleanerDatabaseRepository
{
    private const TABLE = 'ucc_protocol';

    private ilUserCleanerRuleSetRepository $ruleSets;
    private ilUserCleanerRuleSetRuleRepository $memberships;
    private ilUserCleanerRuleRepository $rules;

    public function __construct()
    {
        parent::__construct();
        $this->ruleSets = new ilUserCleanerRuleSetRepository();
        $this->memberships = new ilUserCleanerRuleSetRuleRepository();
        $this->rules = new ilUserCleanerRuleRepository();
    }

    /** @param int[] $matched_rule_set_ids */
    public function start(
        ilObjUser $user,
        ilUserCleanerCleanupAction|string $action,
        array $matched_rule_set_ids
    ): int {
        $action_value = $action instanceof ilUserCleanerCleanupAction ? $action->value : $action;
        if (!in_array($action_value, ['dry_run', 'deactivate', 'delete'], true)) {
            throw new InvalidArgumentException('Unsupported cleanup protocol action.');
        }
        $protocol_id = $this->database->nextId(self::TABLE);
        $this->database->insert(self::TABLE, [
            'protocol_id' => ['integer', $protocol_id],
            'user_id' => ['integer', $user->getId()],
            'matriculation' => ['text', mb_substr($user->getMatriculation(), 0, 255)],
            'firstname' => ['text', mb_substr($user->getFirstname(), 0, 255)],
            'lastname' => ['text', mb_substr($user->getLastname(), 0, 255)],
            'login' => ['text', mb_substr($user->getLogin(), 0, 255)],
            'external_account' => ['text', mb_substr($user->getExternalAccount(), 0, 255)],
            'email' => ['text', mb_substr($user->getEmail(), 0, 255)],
            'action' => ['text', $action_value],
            'status' => ['text', 'pending'],
            'created_at' => ['timestamp', date('Y-m-d H:i:s')],
            'match_details' => ['clob', json_encode(
                $this->getMatchDetails($matched_rule_set_ids),
                JSON_THROW_ON_ERROR
            )],
            'error_message' => ['clob', null],
        ]);

        return $protocol_id;
    }

    public function finish(int $protocol_id, string $status, ?string $error_message = null): void
    {
        if (!in_array($status, ['dry_run', 'success', 'unchanged', 'failed'], true)) {
            throw new InvalidArgumentException('Unsupported cleanup protocol status.');
        }
        $this->database->update(
            self::TABLE,
            [
                'status' => ['text', $status],
                'error_message' => ['clob', $error_message],
            ],
            ['protocol_id' => ['integer', $protocol_id]]
        );
    }

    /**
     * @param array{search?: string, action?: string, date_from?: string, date_to?: string, rule_set?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $records = [];
        foreach ($this->fetchAll('SELECT * FROM ' . self::TABLE . ' ORDER BY created_at DESC, protocol_id DESC') as $row) {
            $record = $this->prepareRecord($row);
            if (($filters['action'] ?? '') !== '' && $record['action'] !== $filters['action']) {
                continue;
            }
            if (($filters['date_from'] ?? '') !== '' && substr((string) $record['created_at'], 0, 10) < $filters['date_from']) {
                continue;
            }
            if (($filters['date_to'] ?? '') !== '' && substr((string) $record['created_at'], 0, 10) > $filters['date_to']) {
                continue;
            }
            if (($filters['rule_set'] ?? '') !== '' && !in_array($filters['rule_set'], $record['rule_set_titles'], true)) {
                continue;
            }
            $search = mb_strtolower(trim($filters['search'] ?? ''));
            if ($search !== '' && !str_contains(mb_strtolower(implode(' ', [
                $record['user_id'],
                $record['matriculation'],
                $record['firstname'],
                $record['lastname'],
                $record['login'],
                $record['external_account'],
                $record['email'],
                $record['rule_sets'],
                $record['rules'],
            ])), $search)) {
                continue;
            }
            $records[] = $record;
        }

        return $records;
    }

    /** @return string[] */
    public function getRuleSetTitles(): array
    {
        $titles = [];
        foreach ($this->getAll() as $record) {
            foreach ($record['rule_set_titles'] as $title) {
                $titles[$title] = $title;
            }
        }
        natcasesort($titles);
        return array_values($titles);
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): void
    {
        $this->database->manipulateF(
            'DELETE FROM ' . self::TABLE . ' WHERE created_at < %s',
            ['timestamp'],
            [$cutoff->format('Y-m-d H:i:s')]
        );
    }

    /** @return array<string, mixed> */
    private function prepareRecord(array $row): array
    {
        try {
            $details = json_decode((string) $row['match_details'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $details = [];
        }
        $set_titles = [];
        $rule_labels = [];
        foreach (is_array($details) ? $details : [] as $set) {
            $set_titles[] = (string) ($set['title'] ?? ('#' . ($set['id'] ?? '')));
            foreach ((array) ($set['rules'] ?? []) as $rule) {
                $label = (string) ($rule['parameter'] ?? '');
                if (($rule['symbol'] ?? '') !== '') {
                    $label .= ' ' . $rule['symbol'] . ' ' . ($rule['value'] ?? '');
                }
                $rule_labels[] = trim($label);
            }
        }

        return $row + [
            'rule_sets' => implode(', ', array_filter($set_titles)),
            'rule_set_titles' => array_values(array_filter($set_titles)),
            'rules' => implode('; ', array_filter($rule_labels)),
        ];
    }

    /**
     * @param int[] $rule_set_ids
     * @return array<int, array<string, mixed>>
     */
    private function getMatchDetails(array $rule_set_ids): array
    {
        $details = [];
        foreach (array_values(array_unique($rule_set_ids)) as $rule_set_id) {
            $rule_set = $this->ruleSets->getById($rule_set_id);
            if ($rule_set === null) {
                continue;
            }
            $rules = [];
            foreach ($this->memberships->getByRuleSetId($rule_set_id) as $membership) {
                $rule = $this->rules->getById($membership->ruleId);
                if ($rule === null) {
                    continue;
                }
                $rules[] = [
                    'id' => $rule->id,
                    'parameter' => $rule->parameter,
                    'symbol' => $rule->symbol,
                    'value' => $rule->value,
                    'source' => $rule->source->value,
                    'source_config_id' => $rule->sourceConfigId,
                ];
            }
            $details[] = [
                'id' => $rule_set->id,
                'title' => $rule_set->title,
                'rules' => $rules,
            ];
        }

        return $details;
    }
}
