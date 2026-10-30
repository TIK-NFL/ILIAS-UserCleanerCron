# User Cleaner

[![License](https://img.shields.io/badge/license-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.en.html)
![Version](https://img.shields.io/badge/version-v0.26.8-green.svg)

> [!CAUTION]
> **WARNING: AUTOMATIC USER DELETION**
>
> UserCleaner can **permanently delete ILIAS user accounts and associated data**.
> Incorrect rules, role selection, or LDAP configuration can cause irreversible
> data loss. **There is no undo function.**
>
> **Do not enable deletion or automatic scheduling in production** until you
> have tested on a development system and an isolated, current production
> clone, reviewed the dry-run candidates, and verified a complete backup by
> restoring it. A database-only backup may not be sufficient.
>
> Read the **[Operations and Safety Guide](doku.md)** before configuring or
> running cleanup. It includes the rollout procedure and go/no-go checklist.

## Overview

**UserCleaner** is an ILIAS 10 CronHook plugin for rule-based user-account
cleanup. It evaluates users in selected global roles and authentication modes.
Rules in a set are combined with `AND`; enabled rule sets are combined with
`OR`. Matching users can be recorded in **dry run**, deactivated, or deleted
through standard ILIAS cleanup.

The plugin is **in development**. Dry run is enabled by default, and the
scheduled cron job is not activated automatically. The plugin and cron ID
are `ucc`.

## Requirements

- ILIAS `10.00`–`10.999` and the corresponding PHP version.
- PHP LDAP support when LDAP rules or tests require it.

## Installation

Place the plugin at:

```text
ILIAS/public/Customizing/global/plugins/Services/Cron/CronHook/UserCleaner/
```

In **Administration > Plugins**, update the plugin slot if needed, then install
and activate UserCleaner. Open its configuration to set up the rule sets,
LDAP interfaces, and **Never delete** exclusions.

> [!WARNING]
> **Leave the User Cleaner cron job disabled initially.** Start with supervised
> dry runs and inspect the protocol. If live processing is justified, test
> **deactivation before deletion**. Do not assume a successful dry run makes a
> later destructive run safe.

## Documentation

The **[full documentation](doku.md)** covers settings, supported rules, LDAP
failure handling, exclusions, protocol exports, cron behavior, lock recovery,
backup and restore requirements, rollout steps, and developer details.

> [!IMPORTANT]
> Unresolved `pending`, `deleting`, or `failed` deletion outcomes require manual
> investigation. A stale execution lock must **never** be cleared solely
> because its heartbeat is old: first verify the owning process has stopped.
> See the [stale-lock procedure](doku.md#releasing-a-stale-execution-lock).

## License

GPL-3.0, following the ILIAS licensing model.
