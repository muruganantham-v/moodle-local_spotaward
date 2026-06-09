# Contributing to local_spotaward

This document covers how to contribute bug fixes and new features to the `local_spotaward` Moodle plugin.

---

## Table of contents

- [Prerequisites](#prerequisites)
- [Getting started](#getting-started)
- [Branching strategy](#branching-strategy)
- [Bug fixes](#bug-fixes)
- [New features](#new-features)
- [Code conventions](#code-conventions)
- [Database changes](#database-changes)
- [Frontend (AMD) changes](#frontend-amd-changes)
- [Commit messages](#commit-messages)
- [Pull request checklist](#pull-request-checklist)

---

## Prerequisites

- Moodle 4.0+ instance with `local_spotaward` installed at `<moodle_root>/local/spotaward/`
- `mod_certificatebeautiful` installed (required for certificate generation)
- PHP 7.4+ matching your Moodle version
- Grunt (optional, only needed if editing AMD source files)

---

## Getting started

1. Clone the repo into your local Moodle installation:
   ```bash
   git clone <repo-url> <moodle_root>/local/spotaward
   ```
2. Run the Moodle upgrade to register any existing schema changes:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```
3. Purge caches after any template, JS, or CSS change:
   ```bash
   php admin/cli/purge_caches.php
   ```

---

## Branching strategy

| Branch type | Naming pattern | Base branch |
|-------------|---------------|-------------|
| Bug fix | `fix/<short-description>` | `main` |
| New feature | `feat/<short-description>` | `main` |
| Refactor | `refactor/<short-description>` | `main` |

Always branch off `main` and open a pull request back into `main`.

---

## Bug fixes

Before starting a fix:

1. Reproduce the bug in a running Moodle instance and note the exact conditions (role, nomination status, course type).
2. Identify which layer owns the bug — business logic (`api.php`), page controller, template, or JS.
3. Read the nomination workflow in CLAUDE.md before touching status transitions. Incorrect transitions corrupt audit data.

When fixing:

- Change only what is necessary. Do not refactor surrounding code in the same commit.
- If the fix touches `api.php`, verify that stale caches are not the root cause first — several methods use class-property caches (`self::$cache_*`) that must be explicitly invalidated when data is refreshed mid-request.
- If the fix changes how a nomination status advances, trace the full path: `pending → approved → ssteamprogress → closed` and confirm the change does not block or skip any step.
- After fixing, manually test the affected role's dashboard and the `submission.php` detail page.

---

## New features

Before writing code:

1. Open an issue or discussion describing the feature, the role it affects, and any new DB state it requires.
2. Confirm the feature fits the existing workflow. The four roles (`nominators`, `programmanagers`, `ssteam`, `manager`) and their capabilities are defined in admin settings — new access control should map to one of the existing capabilities (`local/spotaward:nominate`, `:review`, `:sstask`, `:viewreports`).

When building:

- All business logic belongs in `classes/local/api.php`. Page files (`index.php`, `submission.php`, etc.) must only handle input, call `api`, and render output.
- New admin-configurable values go in `settings.php` and are read via `get_config('local_spotaward', ...)`.
- New notification events must go through `api::send_configured_notification()`. Do not call `email_to_user()` directly from page files.
- New certificate placeholders must be registered in `classes/local/cert_field_map.php`.
- If the feature needs a new page, follow the existing pattern: `require_login()` → capability check → page setup → render. Use `submission.php` as a reference.

---

## Code conventions

- **PHP**: Follow [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle). All strings must go through the `lang/en/local_spotaward.php` language file.
- **SQL**: Use `$DB->get_records_sql()` / `$DB->get_record_sql()` with named placeholders. Never interpolate user input into a query string.
- **Capability checks**: Always use `require_capability()` or `has_capability()` against the correct context before acting on any data. The system context (`context_system::instance()`) is used throughout this plugin.
- **No framework dependencies**: JavaScript must be vanilla ES5/AMD — no npm packages, no transpilation.
- **No comments for obvious code**: Only add a comment when the *why* is non-obvious (a hidden constraint, a Moodle quirk, a workaround). Never describe what the code does.

---

## Database changes

1. Add the change to the appropriate file in `db/`:
   - New table or column → `db/install.xml` (for fresh installs) **and** `db/upgrade.php` (for existing installs).
   - New capability → `db/access.php`.
   - New scheduled task → `db/tasks.php`.
2. Bump `$plugin->version` in `version.php` (format `YYYYMMDDNN`, e.g. `2026060901`).
3. Run the upgrade CLI after changing schema files:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```
4. Never rename or drop a column without a migration step in `upgrade.php`.

---

## Frontend (AMD) changes

AMD source files live in `amd/src/`. The built files in `amd/build/` must be committed alongside source changes.

To rebuild from the Moodle root:
```bash
grunt amd --root=local/spotaward
```

Then purge caches:
```bash
php admin/cli/purge_caches.php
```

Do not edit `amd/build/` files directly. If Grunt is unavailable, note in your PR that a build step is required before merging.

---

## Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/) with the following types:

| Type | When to use |
|------|-------------|
| `fix:` | A bug fix |
| `feat:` | A new feature |
| `refactor:` | Code change with no behaviour change |
| `remove:` | Deleting a feature or dead code |
| `docs:` | Documentation only |
| `db:` | Database schema change |

Format:
```
<type>: <short present-tense description>
```

Examples:
```
fix: nomination stays pending until every student is reviewed
feat: add bulk-approve action to PM dashboard
db: add index on spotaward_nominations.status
```

- Keep the subject line under 72 characters.
- Do not end with a period.
- If the change is not obvious, add a blank line followed by a body explaining *why*, not *what*.

---

## Pull request checklist

Before opening a PR:

- [ ] Branch is based on the latest `main`
- [ ] `version.php` bumped if there are DB changes
- [ ] `db/upgrade.php` updated for any schema changes
- [ ] AMD build files (`amd/build/`) committed if JS was changed
- [ ] Manually tested as each affected role (nominator, PM, SS Team, manager)
- [ ] No hardcoded strings — all user-visible text uses the lang file
- [ ] No direct SQL string interpolation of user input
- [ ] `scripts/deploy_cluster.env` is **not** committed (it is git-ignored)
