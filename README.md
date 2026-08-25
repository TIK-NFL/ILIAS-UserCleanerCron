# User Cleaner

[![License](https://img.shields.io/badge/license-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.en.html)
![Version](https://img.shields.io/badge/version-v0.23.2-green.svg)

`UserCleaner` is an ILIAS CronHook plugin for ILIAS 10. It provides configuration screens for defining user cleanup rules, rule assignments and exclusions.

The plugin id and cron job id are `ucc`.

## Status

This plugin is currently in development. The cron job evaluates local
last-login and LDAP account-existence rules. Dry run is
enabled by default. When explicitly disabled, matching accounts are either
deactivated or deleted through the standard ILIAS user API.

Use it carefully and review the implementation before enabling productive cleanup behaviour.

## Requirements

- ILIAS `10.00` to `10.999`
- PHP as required by the target ILIAS installation
- Optional PHP LDAP extension for LDAP connection tests

## Installation

Copy the plugin into the ILIAS CronHook plugin slot:

```bash
ILIAS/public/Customizing/global/plugins/Services/Cron/CronHook/UserCleaner/
```

Then install or update the plugin in the ILIAS administration:

1. Open `Administration > Plugins`.
2. Run `Update` for the plugin slot if required.
3. Install and activate `UserCleaner`.
4. Open the plugin configuration.
5. Configure rules, assignments, interfaces and exclusions.

## Cron Job

The plugin registers one cron job:

- id: `ucc`
- title: `User Cleaner`
- default schedule: every 1 hour
- auto activation: disabled
- flexible schedule: enabled
- custom settings: enabled

Enable and schedule the cron job through the ILIAS cron job administration after the plugin has been installed.

## Configuration

The plugin configuration is organized into subtabs.

### Settings

`Dry run` is enabled by default, including when no value has been saved yet.
While enabled, matching users may be evaluated but must not be deleted. Disable
it only after reviewing a successful dry run and its protocol records.

The configured action for matching users is either account deactivation or
account deletion using ILIAS's standard cleanup. Deactivation is the safe
default. Protocol retention can optionally be configured in days or months;
cleanup runs with the cron job and removes expired protocol rows and associated
IRSS CSV resources.

### Interfaces

The `Interfaces` tab lists the native ILIAS LDAP configurations available to
LDAP existence rules.

LDAP connections are not duplicated in the plugin. LDAP rules select one of
the existing ILIAS LDAP server configurations, including configurations used
for direct LDAP authentication and configurations used as authentication data
sources.

REST account checks are not implemented. REST rule metadata remains in the
database for forward compatibility. The reserved REST rule remains visible but
disabled in the UI, and any existing REST rule set is skipped safely during
evaluation.

### Rule Sets

The `Rule sets` tab defines cleanup conditions. Every set targets exactly one
global role and exactly one authentication method. All rules inside a set are
combined with `AND`; enabled sets are combined with `OR`.
Existing sets can be opened directly from the rule-set overview. The `Edit`
screen only updates set metadata; `Manage rules` opens the
separate add-rule form and the table of existing AND rules.
New sets are created from the page toolbar in a modal dialog.

Initial parameters are created by the database migration:

- `last_login_months`
- `last_login_days`
- `external_account_missing_ldap`
- `external_account_missing_rest` (reserved; not implemented)

Each implemented rule type records whether its data comes from the local ILIAS
database or an LDAP source. LDAP rules select concrete native ILIAS LDAP
configurations.

Rules consist of:

- parameter
- comparison symbol: `=`, `!=`, `<`, `<=`, `>`, `>=`
- numeric value

The add-rule form uses dependent radio options: time rules expose comparison
fields, while LDAP existence rules expose their applicable source configuration.

Predefined authentication modes:

- `default`
- `ldap`
- `cas`
- `shibboleth`
- `saml`
- `oidc`
- `script`
- `local`

Only global roles are offered as rule-set targets. Legacy rule assignments are
migrated into rule sets and remain stored for transition safety.

### Never Delete

The `Never delete` tab stores users that must be excluded from cleanup. Users are added by login through the ILIAS user autocomplete.

### Protocol

The `Protocol` tab lists persistent cleanup outcomes and identity snapshots.
Its table and search include email addresses alongside IDs, names, matriculation
numbers, logins, external accounts, rule sets and rules. Date, rule-set and action filters
can be combined with the search. The filtered result is exported as UTF-8 CSV,
stored through IRSS, and immediately downloaded.

## Database Tables

The schema is maintained in `sql/dbupdate.php`.

- `ucc_config`: key/value plugin settings
- `ucc_parameter`: available cleanup parameters
- `ucc_rule`: cleanup rules
- `ucc_auth`: authentication modes
- `ucc_execution_rules`: assignments of rules to auth modes and global roles
- `ucc_rule_set`: named rule sets, each targeting one global role and authentication method
- `ucc_rule_set_rule`: ordered rule memberships within rule sets
- `ucc_exclusion`: users excluded from cleanup
- `ucc_protocol`: immutable user snapshots, matched rule details and action outcomes
- `ucc_protocol_export`: IRSS resource references for generated protocol CSV files

Existing `ucc_execution_rules` assignments are retained and migrated into the
rule-set tables during the `0.6.0` update. The legacy table remains available
until the rule-set configuration UI has replaced the old assignment screen.

## Development

Important files:

- `plugin.php`: plugin metadata
- `classes/class.ilUserCleanerPlugin.php`: plugin bootstrap and cron job registration
- `classes/class.ilUserCleanerJob.php`: cron job implementation
- `classes/class.ilUserCleanerConfigGUI.php`: configuration routing and subtabs
- `classes/GUI/`: configuration screens and tables
- `classes/General/class.ilUserDBConnector.php`: database access layer
- `classes/General/class.ilUserCleaner*Repository.php`: typed rule-set, rule, target, source and settings repositories
- `classes/General/class.ilUserCleanerRule*.php`: rule-set domain models
- `sql/dbupdate.php`: database schema and migrations
- `lang/`: German and English language files

Run the PHPUnit suite with
`vendor/composer/vendor/bin/phpunit -c public/Customizing/global/plugins/Services/Cron/CronHook/UserCleaner/phpunit.xml`.
Before productive cleanup, keep dry run enabled and verify candidate records for
the configured roles, authentication methods, rule sets and exclusions.

## License

This plugin follows the ILIAS GPL-3.0 licensing model. See the ILIAS license information for details.
