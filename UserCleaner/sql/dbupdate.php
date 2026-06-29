<#1>
<?php
if (!$ilDB->tableExists('ucc_config')) {
    $ilDB->createTable('ucc_config', [
        'config_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'key' => [
            'type' => 'text',
            'length' => 100,
            'notnull' => true
        ],
        'value' => [
            'type' => 'clob',
            'notnull' => true
        ]
    ]);
    $ilDB->addPrimaryKey('ucc_config', ['config_id']);
}

if (!$ilDB->tableExists('ucc_rule')) {
    $ilDB->createTable('ucc_rule', [
        'rule_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'parameter_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'symbole' => [
            'type' => 'text',
            'length' => 100,
            'notnull' => true
        ],
        'value' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ]
    ]);
    $ilDB->addPrimaryKey('ucc_rule', ['rule_id']);
}

if (!$ilDB->tableExists('ucc_parameter')) {
    $ilDB->createTable('ucc_parameter', [
        'parameter_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'parameter' => [
            'type' => 'text',
            'length' => 100,
            'notnull' => true
        ],
    ]);
    $ilDB->addPrimaryKey('ucc_parameter', ['parameter_id']);
}

if (!$ilDB->tableExists('ucc_auth')) {
    $ilDB->createTable('ucc_auth', [
        'auth_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'auth_mode' => [
            'type' => 'text',
            'length' => 100,
            'notnull' => true
        ],
    ]);
    $ilDB->addPrimaryKey('ucc_auth', ['auth_id']);
}

if (!$ilDB->tableExists('ucc_execution_rules')) {
    $ilDB->createTable('ucc_execution_rules', [
        'execution_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'rule_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'auth_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'role_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
    ]);
    $ilDB->addPrimaryKey('ucc_execution_rules', ['execution_id']);
}

if (!$ilDB->tableExists('ucc_exclusion')) {
    $ilDB->createTable('ucc_exclusion', [
        'exclusion_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'user_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
    ]);
    $ilDB->addPrimaryKey('ucc_exclusion', ['exclusion_id']);
}


?>

<#2>
<?php
foreach ([
    'ucc_config',
    'ucc_rule',
    'ucc_parameter',
    'ucc_auth',
    'ucc_execution_rules',
    'ucc_exclusion',
] as $table_name) {
    if (!$ilDB->sequenceExists($table_name)) {
        $ilDB->createSequence($table_name);
    }
}

?>

<#3>
<?php
foreach ([
    'last_login_months',
    'external_account_missing',
] as $parameter) {
    $result = $ilDB->queryF(
        'SELECT parameter_id FROM ucc_parameter WHERE parameter = %s',
        ['text'],
        [$parameter]
    );

    if (!$ilDB->fetchAssoc($result)) {
        $ilDB->insert('ucc_parameter', [
            'parameter_id' => ['integer', $ilDB->nextId('ucc_parameter')],
            'parameter' => ['text', $parameter],
        ]);
    }
}

?>

<#4>
<?php
if (!$ilDB->tableExists('ucc_auth')) {
    $ilDB->createTable('ucc_auth', [
        'auth_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ],
        'auth_mode' => [
            'type' => 'text',
            'length' => 100,
            'notnull' => true
        ],
    ]);
    $ilDB->addPrimaryKey('ucc_auth', ['auth_id']);
}

if (!$ilDB->sequenceExists('ucc_auth')) {
    $ilDB->createSequence('ucc_auth');
}

?>

<#5>
<?php
foreach ([
    'default',
    'ldap',
    'cas',
    'shibboleth',
    'saml',
    'oidc',
    'script',
    'local',
] as $auth_mode) {
    $result = $ilDB->queryF(
        'SELECT auth_id FROM ucc_auth WHERE auth_mode = %s',
        ['text'],
        [$auth_mode]
    );

    if (!$ilDB->fetchAssoc($result)) {
        $ilDB->insert('ucc_auth', [
            'auth_id' => ['integer', $ilDB->nextId('ucc_auth')],
            'auth_mode' => ['text', $auth_mode],
        ]);
    }
}

?>
