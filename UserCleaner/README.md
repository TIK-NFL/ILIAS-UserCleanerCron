# User Cleaner

[![License](https://img.shields.io/badge/license-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.en.html)
![Version](https://img.shields.io/badge/version-v0.5.4-green.svg)

`UserCleaner` is an ILIAS CronHook plugin for ILIAS 10. It provides configuration screens for defining user cleanup rules, rule assignments and exclusions.

The plugin id and cron job id are `ucc`.

## Status

This plugin is currently in development. The cron job is registered and configurable, but the cleanup logic in `ilUserCleanerJob::run()` is not implemented yet. At the moment, running the cron job returns `OK` without deleting users.

Use it carefully and review the implementation before enabling productive cleanup behaviour.

## Requirements

- ILIAS `10.00` to `10.999`
- PHP as required by the target ILIAS installation
- Optional PHP cURL extension for REST connection tests
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

### Interfaces

The `Interfaces` tab stores optional external account checks.

REST settings:

- status URL template, optionally using `{login}` as placeholder
- optional HTTP Authorization header
- test login for connection checks
- JSON path for the active state
- value that means the external account is active

LDAP settings:

- LDAP host and port
- optional bind DN and bind password
- status attribute
- value that means the LDAP account is active

When saving the form, the plugin tests configured REST and LDAP connections where possible.

### Rules

The `Rules` tab defines cleanup conditions. Initial parameters are created by the database migration:

- `last_login_months`
- `external_account_missing`

Rules consist of:

- parameter
- comparison symbol: `=`, `!=`, `<`, `<=`, `>`, `>=`
- numeric value

### Rule Assignments

The `Rule assignments` tab maps rules to authentication modes and global roles.

Predefined authentication modes:

- `default`
- `ldap`
- `cas`
- `shibboleth`
- `saml`
- `oidc`
- `script`
- `local`

Only global roles are offered for role assignment.

### Never Delete

The `Never delete` tab stores users that must be excluded from cleanup. Users are added by login through the ILIAS user autocomplete.

## Database Tables

The schema is maintained in `sql/dbupdate.php`.

- `ucc_config`: key/value configuration, including REST and LDAP settings
- `ucc_parameter`: available cleanup parameters
- `ucc_rule`: cleanup rules
- `ucc_auth`: authentication modes
- `ucc_execution_rules`: assignments of rules to auth modes and global roles
- `ucc_exclusion`: users excluded from cleanup

## Development

Important files:

- `plugin.php`: plugin metadata
- `classes/class.ilUserCleanerPlugin.php`: plugin bootstrap and cron job registration
- `classes/class.ilUserCleanerJob.php`: cron job implementation
- `classes/class.ilUserCleanerConfigGUI.php`: configuration routing and subtabs
- `classes/GUI/`: configuration screens and tables
- `classes/General/class.ilUserDBConnector.php`: database access layer
- `sql/dbupdate.php`: database schema and migrations
- `lang/`: German and English language files

Before implementing productive cleanup logic, define and test the exact deletion policy. In particular, make sure exclusions, authentication checks, global roles and dry-run or logging behaviour are handled explicitly.

## License

This plugin follows the ILIAS GPL-3.0 licensing model. See the ILIAS license information for details.
