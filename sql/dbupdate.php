<#1>
<?php
$tables = [
    'ucc_config' => [
        'config_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'key' => ['type' => 'text', 'length' => 100, 'notnull' => true],
        'value' => ['type' => 'clob', 'notnull' => true],
    ],
    'ucc_parameter' => [
        'parameter_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'parameter' => ['type' => 'text', 'length' => 100, 'notnull' => true],
        'source_type' => ['type' => 'text', 'length' => 32, 'notnull' => true],
        'value_required' => ['type' => 'integer', 'length' => 1, 'notnull' => true],
        'configuration_required' => ['type' => 'integer', 'length' => 1, 'notnull' => true],
    ],
    'ucc_rule' => [
        'rule_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'parameter_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'symbole' => ['type' => 'text', 'length' => 100, 'notnull' => true],
        'value' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'source_config_id' => ['type' => 'text', 'length' => 255, 'notnull' => false],
    ],
    'ucc_auth' => [
        'auth_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'auth_mode' => ['type' => 'text', 'length' => 100, 'notnull' => true],
    ],
    'ucc_execution_rules' => [
        'execution_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'rule_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'auth_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'role_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
    ],
    'ucc_exclusion' => [
        'exclusion_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'user_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
    ],
    'ucc_rule_set' => [
        'rule_set_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'title' => ['type' => 'text', 'length' => 255, 'notnull' => true],
        'description' => ['type' => 'clob', 'notnull' => true],
        'role_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'enabled' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 1],
        'auth_id' => ['type' => 'integer', 'length' => 4, 'notnull' => false],
    ],
    'ucc_rule_set_rule' => [
        'membership_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'rule_set_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'rule_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'sequence_no' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'source_execution_id' => ['type' => 'integer', 'length' => 4, 'notnull' => false],
    ],
    'ucc_protocol' => [
        'protocol_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'user_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'matriculation' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'firstname' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'lastname' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'login' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'external_account' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'email' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'action' => ['type' => 'text', 'length' => 32, 'notnull' => true],
        'status' => ['type' => 'text', 'length' => 32, 'notnull' => true],
        'created_at' => ['type' => 'timestamp', 'notnull' => true],
        'match_details' => ['type' => 'clob', 'notnull' => true],
        'error_message' => ['type' => 'clob', 'notnull' => false],
    ],
    'ucc_protocol_export' => [
        'export_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'resource_id' => ['type' => 'text', 'length' => 255, 'notnull' => true],
        'filename' => ['type' => 'text', 'length' => 255, 'notnull' => true],
        'created_at' => ['type' => 'timestamp', 'notnull' => true],
        'filters' => ['type' => 'clob', 'notnull' => true],
    ],
];

foreach ($tables as $table => $columns) {
    if (!$ilDB->tableExists($table)) {
        $ilDB->createTable($table, $columns);
        $ilDB->addPrimaryKey($table, [array_key_first($columns)]);
    }
    if (!$ilDB->sequenceExists($table)) {
        $ilDB->createSequence($table);
    }
}

$indexes = [
    'ucc_rule' => [['parameter_id']],
    'ucc_execution_rules' => [['rule_id'], ['auth_id'], ['role_id']],
    'ucc_exclusion' => [['user_id']],
    'ucc_rule_set' => [['role_id'], ['auth_id']],
    'ucc_rule_set_rule' => [['rule_set_id'], ['rule_id'], ['source_execution_id']],
    'ucc_protocol' => [['user_id'], ['login'], ['created_at']],
    'ucc_protocol_export' => [['created_at'], ['resource_id']],
];
foreach ($indexes as $table => $field_sets) {
    foreach ($field_sets as $offset => $fields) {
        if (!$ilDB->indexExistsByFields($table, $fields)) {
            $ilDB->addIndex($table, $fields, 'i' . ($offset + 1));
        }
    }
}

foreach ([
    'last_login_months' => ['local_database', 1, 0],
    'last_login_days' => ['local_database', 1, 0],
    'external_account_missing_ldap' => ['ldap', 0, 1],
    'external_account_missing_rest' => ['rest', 0, 1],
] as $parameter => [$source_type, $value_required, $configuration_required]) {
    $ilDB->insert('ucc_parameter', [
        'parameter_id' => ['integer', $ilDB->nextId('ucc_parameter')],
        'parameter' => ['text', $parameter],
        'source_type' => ['text', $source_type],
        'value_required' => ['integer', $value_required],
        'configuration_required' => ['integer', $configuration_required],
    ]);
}

foreach (['default', 'ldap', 'cas', 'shibboleth', 'saml', 'oidc', 'script', 'local'] as $auth_mode) {
    $ilDB->insert('ucc_auth', [
        'auth_id' => ['integer', $ilDB->nextId('ucc_auth')],
        'auth_mode' => ['text', $auth_mode],
    ]);
}
?>
