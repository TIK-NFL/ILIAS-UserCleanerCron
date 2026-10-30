# User Cleaner

[![License](https://img.shields.io/badge/license-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.en.html)
![Version](https://img.shields.io/badge/version-v0.26.8-green.svg)

## 🚨 WARNING: AUTOMATIC USER DELETION 🚨

> [!CAUTION]
> **This plugin can automatically and permanently delete ILIAS user accounts and their associated data. Incorrect rules or configuration can cause irreversible data loss.**
>
> Before enabling deletion in production, test the complete configuration on a non-production system, run it in dry-run mode, and review the resulting protocol carefully. Make sure that you have a current, verified backup and understand which users and data the standard ILIAS deletion process will remove. You are responsible for validating the cleanup rules for your installation.

Read the [operations and safety guide](doku.md) before configuring a cleanup
job. It documents every setting and rule, complete backup requirements, testing
on development systems and production clones, and the production go/no-go
checklist.

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

LDAP rules select one of the existing ILIAS LDAP server configurations,
including configurations used for direct LDAP authentication and configurations
used as authentication data sources. UserCleaner opens its own short-lived LDAP
connection using those settings because the ILIAS LDAP query wrapper can turn a
failed search into an empty result.

The adapter fails closed: only a successful LDAP response with zero entries is
treated as a missing account. Connection, TLS, bind, timeout, search and result
status errors abort evaluation before cleanup actions begin. UserCleaner only
uses the endpoint currently selected by ILIAS (`ilLDAPServer::getUrl()`). It
does not try the fallback URL list because an obsolete but reachable directory
could return a dangerous successful zero-result search. ILIAS may rotate its
selected endpoint through its own connection management; UserCleaner picks up
that selection on its next run. The ILIAS five-second network timeout is
applied, and individual searches have a 30-second limit. Search filters escape
the external account value. A failed cached connection is discarded before a
later run or lookup can reconnect.

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

While the User Cleaner cron job is running, rule sets and their rules are
read-only. The plugin uses ILIAS's `running_ts` and `alive_ts` cron state for
both the visible controls and server-side write rejection. Previously opened
forms and direct write requests are rejected until the run has finished. A
stale run must be reset through the ILIAS cron administration. Settings and the
`Never delete` list remain available as safety controls.

If a rule-set membership nevertheless resolves to a missing rule during
evaluation, the complete run fails before cleanup actions begin. The evaluator
never continues with a partial AND rule set.

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
- `ecs` (CampusConnect)

Only global roles are offered as rule-set targets.

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
- `ucc_rule_set`: named rule sets, each targeting one global role and authentication method
- `ucc_rule_set_rule`: ordered rule memberships within rule sets
- `ucc_exclusion`: users excluded from cleanup
- `ucc_protocol`: immutable user snapshots, matched rule details and action outcomes
- `ucc_protocol_export`: IRSS resource references for generated protocol CSV files

Update `0.26.4` removes the unused `ucc_execution_rules` table and its legacy
`source_execution_id` membership column. Current cleanup configuration is held
exclusively in `ucc_rule_set` and `ucc_rule_set_rule`.

## Development

Important files:

- `plugin.php`: plugin metadata
- `classes/class.ilUserCleanerPlugin.php`: plugin bootstrap and cron job registration
- `classes/class.ilUserCleanerJob.php`: cron job implementation
- `classes/class.ilUserCleanerConfigGUI.php`: configuration routing and subtabs
- `classes/GUI/`: configuration screens and tables
- `classes/Repository/`: shared database repository support
- `classes/Rule/`: typed rule-set repositories and domain models
- `classes/Settings/`: cleanup settings and exclusion repositories
- `sql/dbupdate.php`: database schema and migrations
- `lang/`: German and English language files

Run the PHPUnit suite with
`vendor/composer/vendor/bin/phpunit -c public/Customizing/global/plugins/Services/Cron/CronHook/UserCleaner/phpunit.xml`.
Before productive cleanup, keep dry run enabled and verify candidate records for
the configured roles, authentication methods, rule sets and exclusions.

## License

This plugin follows the ILIAS GPL-3.0 licensing model. See the ILIAS license information for details.

## Per-user deletion and heartbeat

The cron calls the ILIAS cron manager's `ping('ucc')` at progress points,
throttled to once per minute with a monotonic clock. This refreshes `alive_ts`
and prevents an advancing long run from being classified as crashed after
three hours without a heartbeat. It does not extend PHP time limits or run
while a single database, LDAP or ILIAS deletion call is blocked.

For each deletion candidate the plugin saves one identity snapshot as `pending`,
disables the account through the ILIAS user API, invalidates existing ILIAS
sessions, marks the same row `deleting`, calls standard ILIAS deletion, and
immediately updates that row to `success`. There is no end-of-batch protocol
flush. The overview and CSV include the outcome. Disabling does not cancel
requests that are already executing.

After a hard interruption, `pending` or `deleting` means the outcome is unknown.
Any candidate with an earlier deletion protocol is skipped and reported as
requiring review; no automatic retry or second deletion entry is created.
Failed and unresolved deletion records are exempt from automatic retention
cleanup. There is no automatic reactivation or recovery of partial deletions.
A success-recording failure leaves the previous state intact and emits an error
in the ILIAS log, rather than falsely recording a failed deletion.

An atomic database lock permits only one UserCleaner run across all application
servers. Normal completion and handled failures release the lock. A hard crash
leaves it in place so a later run cannot overlap an uncertain deletion; confirm
that the original process has ended before clearing the lock ownership fields
in `ucc_run_lock`. Keep the singleton row itself. Follow the
[stale-lock release procedure](doku.md#releasing-a-stale-execution-lock).
Bounded batches and fresh eligibility checks are not implemented.
Dry-run entries remain separate observations for each dry run.
