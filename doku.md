# UserCleaner Operations and Safety Guide

## Read this before using the plugin

> [!CAUTION]
> UserCleaner can permanently delete ILIAS accounts and the personal data that
> ILIAS associates with them. A technically valid but incorrect rule can select
> an entire role. The plugin has no undo function for deletion.

Do not install the plugin in production, disable dry run, or activate its cron
job until all steps in this guide have been completed on a development system
and on a current production clone. A database backup alone may not contain all
data affected by ILIAS user deletion. Use the backup and restore procedure for
the complete ILIAS installation.

This guide describes UserCleaner version 0.26.8 for ILIAS 10.

## What the plugin does

UserCleaner evaluates users from configured global ILIAS roles. A rule set also
selects one authentication method. Every rule inside a rule set must match the
user (`AND`). A user is selected when at least one enabled rule set matches
(`OR`).

Depending on the global setting, selected users are only recorded, deactivated,
or deleted through the standard ILIAS user API. For deletion, UserCleaner first
deactivates the account, invalidates its ILIAS sessions, and then calls the
normal ILIAS deletion process. It does not replace or modify that process.

The following accounts are protected from actions:

- the ILIAS anonymous account;
- the ILIAS system account;
- the account executing the cron job;
- users assigned to the global Administrator role;
- users listed under **Never delete**.

The protection check is repeated immediately before an action. Other properties,
including role membership, authentication method, age, and rules, are not all
rechecked before each user. Dry run and the selected action are read once before
the action loop and are not rechecked for every queued user. See
[Known operational limits](#known-operational-limits).

## Configuration reference

### Settings

#### Dry run

Dry run evaluates the enabled rule sets and writes one protocol row for every
matching user. It does not deactivate or delete accounts.

Dry run is enabled when the plugin has no saved setting. Keep it enabled during
configuration, after every rule change, and during the first runs against a
new ILIAS dataset. A dry run is an observation of the data at that time; it is
not an approval token for a later destructive run.

#### Action for matching users

**Deactivate account** sets the ILIAS account to inactive. The account and its
data remain. This prevents a new login, but UserCleaner does not explicitly
invalidate existing sessions for the standalone deactivation action. Use this
as the first live action and confirm the effect with the authentication methods
used by the installation.

**Delete account using standard ILIAS cleanup** performs these steps for each
user:

1. Write a `pending` protocol snapshot.
2. Deactivate the account.
3. Invalidate existing ILIAS sessions.
4. Change the protocol status to `deleting`.
5. Call the standard ILIAS user deletion API.
6. Change the same protocol row to `success`.

ILIAS may remove personal data, mailbox data, workspace data, portfolios, and
other user-related records. Repository objects are handled by ILIAS, including
owner reassignment where applicable. Exact effects depend on the installed
ILIAS version and plugins. Test them on a clone containing representative data.

Any existing deletion protocol for a user prevents UserCleaner from
automatically attempting another deletion for that user ID. `pending`,
`deleting`, and `failed` records require manual review.

#### Protocol retention

The optional positive number and unit define how long protocol rows and stored
CSV exports are retained. Units are days or months. Cleanup happens at the
start of a cron run.

Leave the value empty to retain records indefinitely. Deletion records whose
status is not `success` are retained even when they are older than the configured
period. Successful deletion records, dry-run records, deactivation records,
and old CSV exports may be removed after the period expires.

Protocol retention is not a backup policy. Keep audit evidence outside ILIAS
for as long as required by the organization.

### Interfaces

The Interfaces page shows LDAP configurations already managed by ILIAS.
UserCleaner does not provide a separate LDAP server list.

For LDAP existence rules, the rule must select a concrete ILIAS LDAP
configuration. UserCleaner uses the endpoint currently selected by ILIAS. It
does not try other configured URLs by itself. Connection, TLS, bind, timeout,
search, and LDAP result errors stop evaluation before actions begin. Only a
successful search with zero entries means that an account is absent.

A wrong but reachable LDAP server, base DN, or filter can still return a valid
empty result. That is a configuration error, not a connection failure. Always
test known-present and known-absent external accounts against the selected
configuration.

The REST interface and `external_account_missing_rest` rule are reserved and
not implemented. They cannot be selected for new rules. Existing unsupported
rules are skipped safely and reported by the cron result.

### Rule sets

Each rule set contains:

- **Title**: a recognizable name used in the overview and protocol.
- **Description**: the purpose, owner, and intended population of the set.
- **Target global role**: only users assigned to this global ILIAS role are
  considered.
- **Authentication**: the stored authentication method that users must match.
- **Enabled**: disabled sets do not participate in evaluation.

An enabled rule set without rules is skipped. All rules in a set are combined
with `AND`. Enabled sets are combined with `OR`. If two sets match the same
user, the action is performed once and the protocol records both sets.

Authentication values are based on the user's stored ILIAS authentication
mode:

| Selection | Stored mode considered |
| --- | --- |
| Local ILIAS authentication | `default` |
| LDAP | `ldap` and modes beginning with `ldap_` |
| CAS | `cas` |
| Shibboleth | `shibboleth` |
| SAML | `saml` |
| OpenID Connect | `oidc` |
| External authentication script | `script` |
| System accounts | `local` |
| ECS / CampusConnect | `ecs` |

Do not infer the mode from the login name or email domain. Confirm actual modes
on the clone. Selecting the wrong role or authentication mode can broaden or
empty the candidate list.

While ILIAS reports the UserCleaner cron job as running, or the plugin-owned
execution lock is held, rule-set editing is read-only and write requests are
rejected. A stale execution lock must be cleared only after the underlying
process has been confirmed dead.

### Comparison symbols

Age rules compare the calculated age on the left with the configured numeric
value on the right:

| Symbol | Meaning | Example |
| --- | --- | --- |
| `=` | exactly equal | age is exactly 180 days |
| `!=` | not equal | age is not 6 complete 30-day periods |
| `<` | less than | age is below 180 days |
| `<=` | less than or equal | age is at most 180 days |
| `>` | greater than | age is above 180 days |
| `>=` | greater than or equal | age is at least 180 days |

Month-based rules use complete 30-day blocks: `age_in_months = age_in_days / 30`
rounded down. They do not use calendar-month boundaries. Future timestamps are
treated as age zero.

### Rules

#### Last login in days (`last_login_days`)

Compares the number of complete days since `usr_data.last_login`. A user without
a last-login timestamp does not match this rule, regardless of the selected
comparison. Combine it with **Account has logged in** when the intent should be
especially obvious to reviewers.

Example: `last_login_days >= 180` selects users whose last login was at least
180 complete days ago.

#### Last login in months (`last_login_months`)

Compares complete 30-day blocks since `usr_data.last_login`. A user without a
last-login timestamp does not match.

Example: `last_login_months >= 6` means at least 180 days, not six calendar
months.

#### Account age in days (`account_age_days`)

Compares complete days since `usr_data.create_date`. UserCleaner reads this
column directly from the ILIAS database. If the user object or creation date
cannot be loaded, the user is not processed.

Example: `account_age_days >= 365` selects accounts created at least 365 days
ago.

#### Account age in months (`account_age_months`)

Compares complete 30-day blocks since `usr_data.create_date`.

Example: `account_age_months >= 12` means at least 360 days.

#### Account has logged in (`account_has_logged_in`)

Matches when `usr_data.last_login` contains a non-empty timestamp. It has no
numeric value; the comparison symbol and value are not used for the decision.

#### Account has never logged in (`account_never_logged_in`)

Matches when `usr_data.last_login` is null or empty. It has no numeric value.
It cannot coexist in the same rule set with **Account has logged in**.

Use an account-age rule as an additional `AND` condition. Otherwise,
<span style="color: red;"><strong>newly created accounts can match
immediately.</strong></span>

#### Account no longer exists in LDAP (`external_account_missing_ldap`)

Looks up the user's trimmed external account. If the external account is empty,
the ILIAS login is used. The selected ILIAS LDAP configuration supplies the
endpoint, credentials, base DN, and query settings. LDAP filter values are
escaped.

The rule matches only when the LDAP operation succeeds and returns zero
entries. Operational errors fail the evaluation instead of being interpreted
as a missing account. The rule has no numeric value.

Before using it, test at least:

- a known-present external account, which must not match;
- a known-absent external account, which must match;
- an unreachable endpoint, which must fail the run without actions;
- invalid bind credentials, which must fail the run without actions;
- the exact production base DN and account attribute.

#### Account no longer exists via REST (`external_account_missing_rest`)

Reserved and not implemented. Do not rely on it for cleanup decisions.

### Never delete

This page stores explicit user exclusions. Add critical service accounts,
break-glass accounts, integration owners, and any users under legal or
organizational retention before enabling rule sets.

Exclusions are checked during evaluation and again immediately before each
action. Adding an exclusion during a run can protect a user whose action has not
started yet. It cannot undo an action already in progress.

Review the list after restoring a clone and before every production change.
The list is stored in the plugin database and must be included in backups.

### Protocol

The protocol stores an identity snapshot with user ID, matriculation number,
name, login, external account, email, matched rule sets, matched rules, action,
status, time, and error details. It can be filtered and exported as UTF-8 CSV.

Statuses mean:

| Status | Meaning |
| --- | --- |
| `dry_run` | Candidate recorded; no account change requested |
| `pending` | Action started; outcome not yet confirmed |
| `deleting` | Account disabled and deletion started; final outcome unknown |
| `success` | Requested action completed and was recorded |
| `unchanged` | No change was necessary |
| `failed` | Action failed; changes may be partial |

Treat `pending`, `deleting`, and `failed` as manual-review items. Check whether
the user still exists, inspect ILIAS logs and related data, and document the
decision. UserCleaner will not automatically retry a user ID that already has
a deletion attempt.

The search covers user ID, matriculation number, name, login, external account,
email, rule set, and rule. Results can also be filtered by action, date range,
and rule set. CSV export applies the current filters, stores the generated file
through ILIAS Resource Storage, and downloads it. Export files are subject to
the configured protocol retention period.

### ILIAS cron job

The plugin registers the ILIAS cron job `ucc` with the title **User Cleaner**.
Its suggested schedule is every hour, but automatic activation is disabled.
ILIAS cron administration controls activation, schedule, manual execution, and
stale-run reset.

Installing or activating the plugin must not be treated as permission to
activate the cron schedule. Keep the job disabled while rules are being built.
Use supervised manual runs for dry-run, deactivation, and the first deletion
tests. Enable a schedule only after the production rollout gates have passed.

The cron result summarizes enabled rule sets, matching users, changed users,
unchanged users, failures, and skipped unsupported rule sets. Preserve and
monitor normal ILIAS cron and application logs in addition to the plugin
protocol.

### Atomic execution lock

Before doing any retention cleanup, evaluation, LDAP request, protocol write,
or user action, a run atomically claims the singleton row in `ucc_run_lock`.
Only one database update can change the empty owner token. A competing
UserCleaner run receives a failed cron result and exits without processing
users. The lock applies across application servers that use the same ILIAS
database. Other ILIAS cron jobs are unaffected.

The owner refreshes the lock heartbeat at the same progress points as the ILIAS
cron heartbeat. The lock does not expire automatically. This is intentional:
PHP cannot refresh either heartbeat while a single ILIAS deletion call is
running, and such a call may take several minutes.

Normal completion and handled errors release the lock in a `finally` block. A
hard process or server failure leaves it owned.

#### Releasing a stale execution lock

<span style="color: red;"><strong>Never release the lock only because its
heartbeat is old. First confirm that the recorded process has ended. Releasing
an active lock can start a second UserCleaner run.</strong></span>

To inspect and release a lock left by a hard failure:

1. Stop additional UserCleaner scheduling and manual starts.
2. Inspect the lock owner:

   ```sql
   SELECT owner_token, started_at, heartbeat_at, hostname, process_id
   FROM ucc_run_lock
   WHERE lock_id = 1;
   ```

3. Confirm at operating-system and container level that `process_id` is no
   longer running on `hostname`. Process IDs can be reused, so also compare the
   process start time and command. Heartbeat age alone is not proof.
4. Review `pending` and `deleting` protocol entries and the ILIAS logs.
5. Clear the ownership fields through the approved database-administration
   procedure:

   ```sql
   UPDATE ucc_run_lock
   SET owner_token = '',
       started_at = 0,
       heartbeat_at = 0,
       hostname = '',
       process_id = 0
   WHERE lock_id = 1;
   ```

6. Confirm that the row is free:

   ```sql
   SELECT lock_id, owner_token, started_at, heartbeat_at, hostname, process_id
   FROM ucc_run_lock
   WHERE lock_id = 1;
   ```

   `owner_token` and `hostname` must be empty; all numeric fields must be zero.
7. Keep the singleton row itself. Do not delete it. Reset the ILIAS cron state
   if necessary, then perform a supervised dry run before restoring the
   schedule.

Clearing the lock while its owner is still running can allow simultaneous
UserCleaner executions.

## Backup requirements

Follow the backup procedure for the actual ILIAS deployment. At minimum, a
recoverable backup normally needs all of the following from the same consistent
point in time:

- the complete ILIAS database;
- the ILIAS data directory;
- Resource Storage / IRSS content and any separately mounted file storage;
- relevant web-directory configuration and installed plugin code;
- `ilias.ini.php`, client configuration, and environment-specific secrets or a
  documented way to restore them;
- external storage used by installed plugins when user deletion can affect it.

Keep the backup outside the system being tested. Protect it from modification
and retain it beyond the intended cleanup window.

A backup is not accepted until a restore has been tested:

1. Restore it into an isolated environment.
2. Start ILIAS and log in with an administrative recovery account.
3. Open representative repository objects and user-owned data.
4. Confirm Resource Storage files are available.
5. Confirm the UserCleaner configuration, exclusions, and protocol are present.
6. Delete a disposable user through normal ILIAS administration and confirm the
   expected backup can restore that state.

If the restore procedure is untested, do not enable destructive cleanup.

## Safe rollout procedure

### Phase 1: development system with synthetic users

1. Install and activate UserCleaner. Leave its cron job disabled.
2. Confirm **Dry run** is enabled and the action is **Deactivate account**.
3. Create synthetic users for every relevant authentication mode and target
   role. Do not copy real personal data when synthetic data is sufficient.
4. Include positive and negative controls:
   - an old user who should match;
   - a recent user who must not match;
   - a user in the wrong role;
   - a user with the wrong authentication mode;
   - a never-logged-in account;
   - a user with a recent and an old last login;
   - an Administrator;
   - a user on **Never delete**;
   - for LDAP, known-present and known-absent external accounts.
5. Create one disabled rule set first. Add one rule at a time and document the
   intended population in its description.
6. Enable the rule set and manually run the cron job once in dry-run mode.
7. Export the protocol. Compare every candidate with an independent database or
   identity-management report. Investigate both unexpected matches and expected
   users who are missing.
8. Repeat until the result is stable across representative boundary dates.
9. Disable dry run but keep **Deactivate account**. Run only against disposable
   users and confirm matched controls are inactive while negative controls
   remain active.
10. Test deletion only on disposable accounts containing representative ILIAS
    data. Verify protocol status, repository ownership, mailbox, workspace,
    portfolio, sessions, and installed-plugin data.
11. Restore the environment from backup and repeat the important checks.

### Phase 2: isolated production clone

Create a current clone of the production database and all related file storage.
The clone must not behave like production:

- use a separate hostname, database, data directory, and storage namespace;
- disable all cron jobs before the first application start;
- route email to a sink or disable outbound mail;
- prevent writes to production ECS, SSO, REST, and other integrations;
- restrict administrator and network access to the test team;
- handle copied personal data according to organizational policy;
- use controlled LDAP access and verify which directory is queried.

Then:

1. Install exactly the plugin version intended for production.
2. Recreate the proposed configuration manually or through the approved
   deployment process. Do not blindly copy unexplained development rules.
3. Populate **Never delete** with production-critical accounts.
4. Keep dry run enabled and the UserCleaner cron schedule disabled.
5. Run manually and export the full candidate protocol.
6. Have the service owner and identity-management owner independently review the
   candidate list, role, authentication modes, external accounts, and boundary
   dates.
7. Compare candidate counts with an independent query. Large unexplained count
   changes are a stop condition.
8. Run the deactivation action on the clone and test logins and existing
   sessions for affected and unaffected users.
9. Restore the clone, select deletion, and run again. Confirm every expected
   survivor remains and every expected candidate has one understandable
   protocol outcome.
10. Test an interrupted run and review `pending`, `deleting`, and `failed`
    handling before accepting unattended operation.

### Phase 3: production preparation

Before the first production run:

1. Obtain the required technical, service-owner, data-owner, and privacy
   approvals.
2. Schedule a maintenance or supervised change window.
3. Create and verify a fresh complete backup.
4. Record the plugin version and export or capture every setting, rule set,
   rule, LDAP selection, exclusion, and cron setting.
5. Keep automatic scheduling disabled.
6. Run a fresh production dry run and export its protocol.
7. Compare the result with the approved clone result and an independent report.
8. Stop if the count or identities differ without a documented explanation.
9. Prefer a small, dedicated pilot role or cohort. Start with deactivation.
10. Monitor the ILIAS cron result, application logs, authentication behaviour,
    and UserCleaner protocol.
11. Enable deletion only after the pilot has been reviewed and the restore plan
    is still valid.
12. Enable a schedule only after multiple supervised manual runs have produced
    predictable results.

## Go/no-go checklist

Proceed with a destructive run only when every answer is **yes**:

- Is dry run currently producing the approved candidate list?
- Were expected survivors represented in the test data and confirmed absent
  from the candidate list?
- Are role membership and stored authentication modes independently verified?
- Are Administrator, system, integration, legal-hold, and break-glass accounts
  protected?
- Are LDAP known-present, known-absent, and failure cases verified when used?
- Is the candidate count within an approved range?
- Was the complete backup taken after the final configuration change?
- Was that backup procedure restored successfully on an isolated system?
- Is someone available to inspect unresolved outcomes and stop later runs?
- Is automatic scheduling still disabled for the first destructive run?

If any answer is no, leave dry run enabled and do not proceed.

## Stop conditions

Stop rollout and investigate when any of the following occurs:

- an Administrator, system account, excluded user, wrong role, or wrong
  authentication method appears as a candidate;
- the candidate count changes unexpectedly;
- LDAP returns an unexpected successful zero-result response;
- the cron result reports skipped rule sets, failures, or review-required
  actions;
- protocol rows remain `pending` or `deleting` after the process has ended;
- the heartbeat or ILIAS cron state becomes stale;
- one user deletion takes materially longer than tested;
- the backup or restore verification is incomplete;
- the production configuration differs from the approved clone.

## Known operational limits

- UserCleaner cannot roll back an ILIAS deletion.
- A heartbeat is emitted between work items and is throttled to once per minute.
  It cannot run while a single LDAP, database, or ILIAS deletion call is blocked.
- A long ILIAS deletion can therefore appear silent until it returns.
- An atomic database lock permits only one UserCleaner run. It is deliberately
  not expired from heartbeat age because one ILIAS deletion may run for several
  minutes without returning control to the plugin.
- A hard crash leaves the execution lock in place and requires deliberate
  administrative recovery after the process has been confirmed dead.
- Runs have no plugin-defined batch size or elapsed-time budget.
- There is no graceful stop switch between users.
- Rule membership, authentication mode, age, and all settings are not freshly
  evaluated before every action. Protected-account and exclusion checks are
  repeated.
- `pending` and `deleting` indicate an uncertain outcome after interruption;
  there is no automatic reconciliation.
- Rule editing is locked during a reported run, but settings and exclusions can
  still be changed.
- REST account-existence checks are not implemented.

Treat these limits as part of the production risk assessment. They do not
replace clone testing or backup verification.

## Installation and developer reference

### Compatibility and installation

- ILIAS versions: `10.00` to `10.999`.
- PHP: the version required by the target ILIAS installation.
- PHP LDAP extension: optional for LDAP connection tests; required for LDAP functionality where applicable.

Install the plugin in the ILIAS CronHook plugin slot:

```text
ILIAS/public/Customizing/global/plugins/Services/Cron/CronHook/UserCleaner/
```

In ILIAS administration, open `Administration > Plugins`, update the plugin
slot if required, then install and activate **UserCleaner**. Open the plugin
configuration to set up rules, assignments, interfaces, and exclusions.
Installation or activation does **not** authorize activation of the cron job.

### Configuration interface details

- **Rule sets**: Create sets using the page-toolbar modal. Open an existing set
  from the overview; **Edit** updates metadata, while **Manage rules** shows
  the add-rule form and current `AND` rules. The add-rule form shows comparison
  fields for time rules and source configuration for LDAP rules.
- **Never delete**: Add users by login using the ILIAS user autocomplete.
- **Interfaces**: Native ILIAS LDAP configurations can include both direct LDAP
  authentication servers and authentication data sources.
- **Protocol**: Search and filter by identity, rules, action, and dates before
  exporting a UTF-8 CSV through ILIAS Resource Storage (IRSS).

Database migration metadata initially included `last_login_months`,
`last_login_days`, `external_account_missing_ldap`, and the reserved,
nonfunctional `external_account_missing_rest` parameter. The current rule
reference above also documents additional available rule types. Rules record
whether their data comes from ILIAS or LDAP; applicable LDAP rules require a
specific native LDAP configuration. Only global roles may be targets.

If a rule set references a rule that is missing during evaluation, the entire
run fails before any cleanup actions begin; an incomplete `AND` set is never
partially evaluated.

### LDAP implementation details

UserCleaner uses a short-lived LDAP connection rather than interpreting an
unsuccessful ILIAS LDAP wrapper search as an empty result. The configured
server is the endpoint selected by ILIAS (`ilLDAPServer::getUrl()`). The plugin
never iterates fallback URLs itself. ILIAS may update its selected endpoint,
which UserCleaner reads on a later run.

The ILIAS five-second network timeout and a 30-second limit for individual
searches are applied. Search filters escape external-account values. A failed
cached connection is discarded so that a later lookup or run can reconnect.
Only a successful zero-entry result is evidence of absence. A successful
lookup against a *wrong* but reachable directory is still dangerous; validate
the destination and search scope independently.

REST rule metadata is retained for forward compatibility. The reserved REST
rule is visible but disabled in the UI; existing unsupported rule sets are
skipped and reported.

### Cron job registration and heartbeat

- Plugin ID and cron job ID: `ucc`.
- Cron job title: **User Cleaner**.
- Suggested/default schedule: hourly; automatic activation disabled.
- Flexible scheduling and custom settings are supported.

The cron calls the ILIAS cron manager's `ping('ucc')` at progress points,
throttled to once per minute using a monotonic clock. This refreshes the
ILIAS `alive_ts` state, but does not extend PHP time limits or execute while
one database, LDAP, or deletion call is blocked. The rule-set editing guard
also uses ILIAS `running_ts` and `alive_ts`; previously opened forms and
direct writes are rejected during a reported run. Settings and **Never delete**
remain available as safety controls.

For deletion, protocol status transitions happen per user rather than as a
batch flush. A failure to record `success` retains the preceding status and
is logged, rather than incorrectly marking deletion as failed. A hard
interruption may leave `pending` or `deleting` records requiring review;
automatic recovery, reactivation, and retry are not implemented. Disabling an
account does not cancel requests already executing. Separate dry runs create
separate observations.

### Database tables

The schema and migrations are defined in `sql/dbupdate.php`.

| Table | Purpose |
| --- | --- |
| `ucc_config` | Plugin settings (key/value) |
| `ucc_parameter` | Available cleanup parameters |
| `ucc_rule` | Cleanup rules |
| `ucc_auth` | Authentication modes |
| `ucc_rule_set` | Named sets with a global role and authentication method |
| `ucc_rule_set_rule` | Ordered rule memberships within sets |
| `ucc_exclusion` | Protected user exclusions |
| `ucc_protocol` | Identity snapshots, match details, and action outcomes |
| `ucc_protocol_export` | IRSS references for exported CSV files |
| `ucc_run_lock` | Singleton atomic execution lock |

Migration `0.26.4` removes the unused `ucc_execution_rules` table and its
legacy `source_execution_id` membership column. Current set membership is
stored in `ucc_rule_set` and `ucc_rule_set_rule`.

### Source tree and tests

| Path | Role |
| --- | --- |
| `plugin.php` | Plugin metadata |
| `classes/class.ilUserCleanerPlugin.php` | Bootstrap and cron registration |
| `classes/class.ilUserCleanerJob.php` | Cron implementation |
| `classes/class.ilUserCleanerConfigGUI.php` | Configuration routing and subtabs |
| `classes/GUI/` | Configuration screens and tables |
| `classes/Repository/` | Shared database repository support |
| `classes/Rule/` | Typed rule-set repositories and domain models |
| `classes/Settings/` | Settings and exclusion repositories |
| `sql/dbupdate.php` | Database schema and migrations |
| `lang/` | German and English translations |

Run the PHPUnit suite from the ILIAS installation:

```bash
vendor/composer/vendor/bin/phpunit -c public/Customizing/global/plugins/Services/Cron/CronHook/UserCleaner/phpunit.xml
```

### License

UserCleaner follows the ILIAS GPL-3.0 licensing model. See the ILIAS license
information and the [GPLv3 license](https://www.gnu.org/licenses/gpl-3.0.en.html).
