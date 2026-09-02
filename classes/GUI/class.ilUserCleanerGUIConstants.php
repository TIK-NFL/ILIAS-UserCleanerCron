<?php
declare(strict_types=1);

final class ilUserCleanerGUIConstants
{
    public const COMPONENT_PARAMETERS = ['ctype', 'cname', 'slot_id', 'plugin_id', 'pname'];

    public const CMD_SHOW = 'show';
    public const CMD_SAVE = 'save';
    public const CMD_ADD_RULE = 'addRule';
    public const CMD_SAVE_RULE_SET = 'saveRuleSet';
    public const CMD_EDIT_RULE_SET = 'editRuleSet';
    public const CMD_MANAGE_RULE_SET_RULES = 'manageRuleSetRules';
    public const CMD_UPDATE_RULE_SET = 'updateRuleSet';
    public const CMD_DELETE_RULE_SET = 'deleteRuleSet';
    public const CMD_CONFIRM_DELETE_RULE_SET = 'confirmDeleteRuleSet';
    public const CMD_DELETE_RULE_SET_RULES = 'deleteRuleSetRules';
    public const CMD_ADD_EXCLUSION = 'addExclusion';
    public const CMD_USER_AUTOCOMPLETE = 'doUserAutoComplete';
    public const CMD_HANDLE_TABLE_ACTIONS = 'handleTableActions';
    public const CMD_APPLY_PROTOCOL_FILTER = 'applyProtocolFilter';
    public const CMD_RESET_PROTOCOL_FILTER = 'resetProtocolFilter';
    public const CMD_EXPORT_PROTOCOL = 'exportProtocol';

    public const TABLE_ACTION_DELETE = 'delete';
    public const PARAM_RULES_TABLE_ACTION = 'rules_table_action';
    public const PARAM_RULE_IDS = 'rules_table_rule_ids';
    public const PARAM_RULE_SET_ID = 'rule_set_id';
    public const PARAM_RULE_SET_RULE_IDS = 'rule_set_rules_table_membership_ids';
    public const PARAM_EXCLUSION_TABLE_ACTION = 'exclusion_table_action';
    public const PARAM_EXCLUSION_IDS = 'exclusion_table_exclusion_ids';

    public const FIELD_PARAMETER_ID = 'parameter_id';
    public const FIELD_SYMBOL = 'symbol';
    public const FIELD_VALUE = 'value';
    public const FIELD_RULE_ID = 'rule_id';
    public const FIELD_AUTH_ID = 'auth_id';
    public const FIELD_ROLE_ID = 'role_id';
    public const FIELD_USER_LOGINS = 'user_logins';
    public const FIELD_SOURCE_CONFIG_ID = 'source_config_id';
    public const FIELD_RULE_SET_TITLE = 'rule_set_title';
    public const FIELD_RULE_SET_DESCRIPTION = 'rule_set_description';
    public const FIELD_RULE_SET_ENABLED = 'rule_set_enabled';
    public const FIELD_DRY_RUN = 'dry_run';
    public const FIELD_CLEANUP_ACTION = 'cleanup_action';
    public const FIELD_PROTOCOL_SEARCH = 'protocol_search';
    public const FIELD_PROTOCOL_ACTION = 'protocol_action';
    public const FIELD_PROTOCOL_DATE_FROM = 'protocol_date_from';
    public const FIELD_PROTOCOL_DATE_TO = 'protocol_date_to';
    public const FIELD_PROTOCOL_RULE_SET = 'protocol_rule_set';
    public const FIELD_PROTOCOL_RETENTION_VALUE = 'protocol_retention_value';
    public const FIELD_PROTOCOL_RETENTION_UNIT = 'protocol_retention_unit';

    public const RULE_SYMBOLS = [
        '=' => '=',
        '!=' => '!=',
        '<' => '<',
        '<=' => '<=',
        '>' => '>',
        '>=' => '>=',
    ];

    private function __construct()
    {
    }
}
