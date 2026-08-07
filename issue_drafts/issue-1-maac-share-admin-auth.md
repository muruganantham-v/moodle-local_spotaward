## Summary
`share_admin.php` allows the assigned MAAC Executive to submit the Share to Admin form, but `api::send_pr_document_to_admin()` rejects the same user unless they are SS Team or site admin.

## Affected code
- `share_admin.php:17`
- `classes/local/api.php:1342`

## Steps to reproduce
1. Log in as a MAAC Executive assigned to a nomination in `ssteamprogress`.
2. Open `/local/spotaward/share_admin.php?id={nominationid}`.
3. Upload a PR PDF and submit.

## Actual result
The page accepts the MAAC Executive as an allowed actor, but the API throws `notauthorised` because `send_pr_document_to_admin()` only allows SS Team or site admin.

## Expected result
The same role check should be enforced end-to-end. Either:
- MAAC Executive should be allowed to complete the share flow, or
- the page should block them before showing the form.

## Why this matters
This is a broken primary workflow on the Share to Admin page and creates confusing behavior for production users.
