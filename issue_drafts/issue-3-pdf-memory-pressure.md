## Summary
Certificate merge/share flows load every certificate PDF fully into PHP memory, often more than once, which can create high RAM usage, long request times, and failures on production servers.

## Affected code
- `classes/local/api.php:3934`
- `classes/local/api.php:3943`
- `classes/local/api.php:4305`
- `classes/local/api.php:4313`
- `view_certificate.php:78`

## Current behavior
Several paths collect all certificate contents with `get_content()` into arrays before merging:
- Share to Admin combined PDF
- Share to Admin compact fallback generation
- Admin dashboard bulk download
- View/download merged certificates

The admin-share path can also regenerate certificates again in compact mode after already building the first merged PDF.

## Expected behavior
Large certificate batches should avoid loading all PDFs into memory at once. Safer approaches include:
- streaming temp files into the merger
- page-by-page processing
- chunked merge jobs
- background/adhoc tasks for large batches

## Why this matters
This plugin is used in web requests. Large in-memory PDF work can:
- spike PHP memory
- increase response latency
- make the plugin feel heavy on production
- trigger hard-to-debug failures under load
