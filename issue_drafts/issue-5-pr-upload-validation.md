## Summary
The Share to Admin PR upload relies on the form picker’s accepted file type, but there is no server-side validation that the uploaded file is actually a PDF before it is emailed onward.

## Affected code
- `forms/share_admin_form.php:30`
- `share_admin.php:44`
- `classes/local/api.php:4124`
- `classes/local/api.php:4175`

## Current behavior
The UI only advertises `accepted_types => ['.pdf']`, but the backend accepts the saved upload path/content as-is and packages it into outgoing admin emails.

## Expected behavior
The plugin should enforce PDF validation server-side, for example by checking:
- filename extension
- MIME type
- PDF signature/content sanity

## Why this matters
Client-side file restrictions are not enough. Without backend validation, non-PDF content can be forwarded as an approved admin-share attachment.
