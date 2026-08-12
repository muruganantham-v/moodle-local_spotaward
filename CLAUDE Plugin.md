# Project Rules for AI Assistant — Moodle Plugin Development

> Drop this file in your project root as `CLAUDE.md` (Claude Code reads it automatically).
> For other tools: rename to `.cursorrules`, or paste into your system prompt / custom instructions.
> Fill in the `[ ... ]` placeholders once your plugin details are set.

## 0. Project Context

- **Plugin type:** [ e.g. mod (activity), block, local, report, tool, theme, auth, etc. ]
- **Frankenstyle component name:** [ e.g. `mod_yourplugin`, `local_yourplugin`, `block_yourplugin` — must match the folder name and every reference in code ]
- **Target Moodle version(s):** [ e.g. supports 4.1 – 4.5 ]
- **PHP version:** [ match what your target Moodle versions require ]
- Before making changes, check `version.php` for the current `$plugin->component`, `$plugin->version`, and `$plugin->requires` — keep them accurate.

---

## 1. Code Style & Conventions (Moodle Coding Style)

- Follow the official Moodle Coding Style — run `phpcs` with the `moodle` standard before considering anything done: `[ e.g. vendor/bin/phpcs --standard=moodle . ]`
- 4-space indentation, no tabs. No trailing whitespace.
- Every PHP file starts with the standard Moodle GPL license header block and `defined('MOODLE_INTERNAL') || die();` where applicable.
- No hardcoded user-facing strings — every string shown to a user goes through `get_string('key', 'component')`, defined in `lang/en/<component>.php`.
- New classes go under `classes/`, PSR-4 autoloaded, namespaced as `\<component>\...`. Don't add new global-namespace functions except required `xxx_component_*` callbacks in `lib.php`.
- Function/class/file names use `snake_case`, matching Moodle core conventions.
- Match existing patterns in the plugin before inventing new ones.

## 2. Security Checks

Before finishing any task, verify:
- No direct use of `$_GET`, `$_POST`, `$_REQUEST`, or `$_COOKIE` — always `required_param()` / `optional_param()` with an explicit `PARAM_*` type.
- Every page/script checks `require_login()` and `require_capability()` before doing anything sensitive.
- Every state-changing action checks `sesskey` — use `moodleform` or `require_sesskey()`.
- All database access goes through the `$DB` API with placeholders — never raw string-concatenated SQL.
- All output is escaped correctly for context: `s()`, `format_string()`, `format_text()` — never raw `echo` of user input.
- New capabilities defined in `db/access.php` with least-privilege defaults.
- Privacy API (`classes/privacy/provider.php`) implemented/updated if personal data is stored.

## 3. Testing & Quality — Full QA Pass

Treat every change as if it will be reviewed by a QA team that has seen every way a plugin can break. Don't just test that the feature works — test that it fails safely, at scale, for every role, and after the site has been running for years. Go through **every relevant category below**, not just the ones that seem obviously related to the change.

### 3.1 Functional — Happy Path & Beyond
- The core feature works exactly as intended, end to end, not just in isolation.
- Every button, link, and form field actually does what its label says.
- Works the same whether reached via direct URL, navigation menu, or another plugin's link into it.
- Re-test after the *next* action too — does the page after this one still render correctly?

### 3.2 Boundary & Edge-Case Input
- Empty input, null values, and missing optional fields — no fatal errors, sensible defaults or clear messages.
- Minimum and maximum allowed values (0, negative numbers, exactly the limit, one over the limit).
- Extremely long strings (10,000+ characters) — no layout breakage, no DB truncation errors.
- Unicode, emoji, RTL text (Arabic/Hebrew), and mixed-direction text in every text field.
- Special characters that could break HTML/SQL if mishandled: `<script>`, `' OR 1=1 --`, `{{mustache}}`, quotes, ampersands, newlines.
- Duplicate submissions — double-clicking submit, or resubmitting via browser back button, doesn't create duplicate records.
- File uploads: zero-byte files, oversized files, wrong file type, files with no extension, files with double extensions (`evil.php.jpg`), filenames with spaces/unicode/path traversal characters (`../../etc/passwd`).
- Date/time edge cases: leap years, Feb 29, DST transitions, timezone differences between server and user, dates at year boundaries (Dec 31 → Jan 1).

### 3.3 Role & Permission Testing
Test as **every** relevant role, not just admin:
- Not logged in (guest access, if applicable)
- Guest role
- Student / participant
- Non-editing teacher
- Teacher / editing teacher
- Manager
- Site administrator
- Any custom role defined by the plugin
- For each role: confirm they see only what they should, can only do what they're permitted to, and get a clear "access denied" (not a fatal error) when they try something they shouldn't.
- Test with a capability explicitly set to `Prevent` and to `Prohibit`, not just the default.

### 3.4 Concurrency & Race Conditions
- Two users performing the same action at the same time (e.g. both submitting, both editing) doesn't corrupt data or throw an unhandled exception.
- Long-running actions (large imports, report generation) don't block other users or time out silently.
- Session expiring mid-action (e.g. mid-form-submission) fails gracefully with a clear message, not a blank page or fatal error.

### 3.5 Data Integrity & Lifecycle
- Deleting a parent object (course, course module, user) correctly cleans up or handles the plugin's related data — no orphaned rows.
- Restoring a deleted user/course doesn't leave the plugin in an inconsistent state.
- Data survives a full backup → restore cycle intact, including into a *different* course/site (cross-site restore).
- Reports/exports match what's actually in the database — spot-check the numbers, don't trust the UI alone.

### 3.6 Install / Upgrade / Uninstall Lifecycle
- Fresh install on a clean site works with no errors or warnings.
- Upgrade path from each previous major version you support — not just from the immediately prior one.
- Running the upgrade twice in a row (idempotency) doesn't error or duplicate schema changes.
- Uninstalling the plugin removes its DB tables, config, capabilities, and scheduled tasks — no leftovers in `mdl_config_plugins` or orphaned tables.
- Plugin can be safely disabled and re-enabled without data loss.

### 3.7 Privacy / GDPR
- A user's data export (Privacy API) includes everything the plugin stores about them, in a readable format.
- A user's data deletion request actually deletes or anonymizes their data per the plugin's privacy metadata declaration.
- Deleting one user's data doesn't affect other users' data.

### 3.8 Internationalization
- Every user-facing string is externalized — search for hardcoded English text before calling a task done.
- Switch the site/user language to a non-English pack (including an RTL language like Arabic) and confirm layout doesn't break.
- Long translated strings (German, Finnish) don't overflow buttons/labels.
- Number and date formats respect locale settings, not hardcoded US format.

### 3.9 Accessibility (Moodle requires WCAG 2.1 AA)
- Full keyboard navigation — every action reachable and usable without a mouse, in a logical tab order.
- Screen reader sanity check: form fields have labels, images have alt text, dynamic content updates are announced.
- Color isn't the only way information is conveyed (e.g. error states also have text/icon, not just red color).
- Sufficient color contrast, especially in custom-styled elements.
- Focus is visibly indicated and moves sensibly after actions (e.g. after form submit, after modal close).

### 3.10 Performance & Scale
- Test with a realistic large dataset (thousands of records), not just the 3 rows used during development.
- Check for N+1 query patterns — one query per row in a loop instead of a single batched query.
- Pages with lists/reports use pagination, not loading everything at once.
- Scheduled tasks (cron) complete in reasonable time and don't lock tables for long periods.
- No queries missing indexes on large tables — check `db/install.xml` for indexes on frequently-filtered columns.

### 3.11 Compatibility
- Works on the standard Moodle themes shipped with core (Boost and any others in use on the target site).
- Works in the Moodle mobile app if the plugin has any mobile-relevant surface (check if `db/mobile.php` / mobile addon is needed).
- Cross-browser check on at least Chrome, Firefox, Safari — layout and JS behavior consistent.
- Responsive layout: test at mobile, tablet, and desktop widths, not just full desktop.
- Doesn't conflict with commonly co-installed plugins if the plugin hooks into shared APIs (e.g. course format, block regions).

### 3.12 Error Handling & Logging
- Errors are caught and shown as a proper Moodle `moodle_exception` with a helpful message — never a raw PHP fatal/stack trace to the end user.
- Errors are logged appropriately (`debugging()` calls, event logging) so admins can diagnose issues without user-supplied repro steps.
- Event observers/loggers fire correctly and don't throw if an optional related object is missing.

### 3.13 Automated Test Coverage
- PHPUnit tests in `tests/`, using `advanced_testcase` and Moodle data generators, covering: happy path, at least one edge case per input field, and at least one permission-denied case.
- Behat feature tests for every user-facing flow, including at least one "as a student" and one "as a teacher/admin" scenario.
- A failing test is written *before* the fix for any reported bug, so the fix is provably correct and can't silently regress.
- Run before considering anything done: `[ e.g. vendor/bin/phpcs --standard=moodle . && vendor/bin/phpunit && php admin/tool/behat/cli/run.php ]`

## 4. Project Structure & Architecture

- `version.php`, `lib.php`, `settings.php`, `db/install.xml`, `db/upgrade.php`, `db/access.php`, `db/caches.php`, `lang/en/<component>.php`, `classes/`, `templates/`, `tests/`, `backup/moodle2/` — keep new code in the matching location.
- View scripts stay thin; push logic into `classes/`.
- Renderers use Mustache templates, not inline HTML.

## 5. Before Marking Any Task Done — Checklist

- [ ] `phpcs` (moodle standard) passes with no new warnings
- [ ] PHPUnit tests added/updated and passing
- [ ] Behat test added if a UI flow changed
- [ ] Tested as every relevant role (guest, student, teacher, manager, admin)
- [ ] Boundary/edge-case inputs tried (empty, max-length, special characters, unicode)
- [ ] Capability, sesskey, and login checks present on new/changed pages and actions
- [ ] All DB access uses the `$DB` API — no raw SQL
- [ ] All user-facing text goes through `get_string()`
- [ ] Tested with a non-English/RTL language pack
- [ ] Keyboard-only navigation works for any new UI
- [ ] Behavior checked at realistic data scale, not just a handful of rows
- [ ] `version.php` bumped if schema, capabilities, or metadata changed
- [ ] `db/upgrade.php` savepoint added if `install.xml` changed, and upgrade path tested
- [ ] Uninstall tested — no orphaned tables/config left behind
- [ ] Privacy API updated if new personal data is stored, and export/delete tested
- [ ] Backup → restore tested if the plugin stores course/user data
- [ ] No unrelated files changed
- [ ] Any assumptions made are stated explicitly, not silently guessed

## 6. Do Not

- Do not access `$_GET`/`$_POST`/`$_REQUEST` directly — use `required_param()`/`optional_param()`.
- Do not write raw or string-concatenated SQL.
- Do not hardcode UI strings — always `get_string()`.
- Do not skip capability/`sesskey` checks "to make testing easier."
- Do not echo raw user input without `s()`/`format_string()`/`format_text()`.
- Do not omit the GPL license header from new PHP files.
- Do not change `db/install.xml` without a matching `db/upgrade.php` step.
- Do not mark a task "done" after testing only the happy path as a single role with tiny sample data — that is not a complete test.
