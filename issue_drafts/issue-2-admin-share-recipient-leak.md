## Summary
Admin-share emails are still sent to the nominator and program manager in addition to Admin-role recipients, which leaks the uploaded PR document and certificate attachments outside the intended Admin audience.

## Affected code
- `classes/local/api.php:1350`
- `classes/local/api.php:1354`
- `classes/local/api.php:1358`

## Current behavior
`send_pr_document_to_admin()` starts with `get_admin_users()`, then appends:
- the nominator
- the program manager

`send_configured_notification()` then emails the same attachment bundle to every deduplicated recipient.

## Expected behavior
When a record is shared to Admin, only Admin-role recipients should receive the PR and certificates unless there is an explicit, separately configurable CC feature.

## Why this matters
This is a privacy and data-distribution issue:
- PR documents may contain internal or financial information.
- Certificate bundles contain student data.
- The plugin behavior no longer matches the “shared with Admin” wording in the UI and requirements.
