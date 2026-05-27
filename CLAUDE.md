# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`local_spotaward` is a Moodle local plugin (component name `local_spotaward`, version 1.1.0, BETA) that manages a Spot Award nomination workflow: mentors nominate students → program managers review → SS Team processes → certificates are generated and distributed.

Install this plugin at `<moodle_root>/local/spotaward/`.

## Development commands

There is no npm/composer/build step. AMD modules in `amd/src/` must be manually minified to `amd/build/` (Moodle's standard grunt setup applies if the host Moodle has it):

```bash
# From Moodle root — rebuild AMD for this plugin only
grunt amd --root=local/spotaward

# Run Moodle CLI upgrade after schema changes
php admin/cli/upgrade.php --non-interactive

# Purge caches after template/JS/CSS changes
php admin/cli/purge_caches.php
```

### Multi-server deploy

```bash
cp scripts/deploy_cluster.env.example scripts/deploy_cluster.env
# edit deploy_cluster.env with your server details
./scripts/deploy_cluster.sh --config ./scripts/deploy_cluster.env [--with-maintenance] [--dry-run]
```

## Architecture

### Nomination workflow and statuses

```
pending → (PM approves) → approved → (SS Team) → ssteamprogress → closed
        ↘ (PM rejects) → rejected
```

Four roles drive access control (configured in plugin admin settings, defaulting to role shortnames):
- **nominators** — submit nominations (`local/spotaward:nominate`)
- **programmanagers** — review/approve/reject (`local/spotaward:review`)
- **ssteam** / MAAC Executives — process approved nominations, generate/share certificates (`local/spotaward:sstask`)
- **manager** — view reports (`local/spotaward:viewreports`)

### Core PHP files

| File | Purpose |
|------|---------|
| `classes/local/api.php` | Single `final class api` — all static methods. All business logic lives here: role checks, nomination CRUD, email + Zoho Cliq notifications, certificate PDF generation via mPDF, Beautiful Certificate integration. |
| `classes/local/constants.php` | Domain constants: role ID lookups, module list, course→module mapping, award category lists (standard vs. advanced-C), description templates. |
| `classes/local/cert_field_map.php` | Field→placeholder mapping for certificate templates (`{student_name}`, `{{spotaward.roll_no}}`, etc.). |
| `lib.php` | Moodle plugin hooks (`local_spotaward_extend_navigation`), plus PHP helper functions used across pages: `local_spotaward_render_data_table()`, inline CSS/JS injection for the success overlay and nomination widget. |
| `settings.php` | Admin settings page: role selectors, allowed course prefixes, admin team members, Zoho Cliq credentials, certificate template selector. |

### Entry-point pages

| Page | Role | Description |
|------|------|-------------|
| `index.php` | Nominator | Nomination form, draft preview, submission |
| `submission.php` | PM / SS Team | View nomination detail, approve/reject, upload PR doc, share certificates |
| `report.php` | Manager | Reporting dashboard |
| `close_record.php` | SS Team | Close a nomination record |
| `share_admin.php` | SS Team | Upload PR doc and email to admin team |
| `email_templates.php` | Admin | Edit email/Cliq notification templates |
| `view_certificate.php` | Any authorised | View/download a student certificate |
| `ajax.php` | AJAX | Student report modal content, AJAX actions |

### Database tables

- **`spotaward_nominations`** — one row per nomination batch (nominator, course, PM, MAAC exec, status)
- **`spotaward_nomination_items`** — one row per student per award category within a nomination
- **`spotaward_status_track`** — audit trail of every status transition
- **`spotaward_cert_backgrounds`** — cached background images extracted from Beautiful Certificate templates

### Frontend

All JavaScript is vanilla ES5/AMD — no framework, no npm build required.

- `amd/src/table_tools.js` — client-side sortable/filterable/exportable table (initialised by `local_spotaward_render_data_table()` in lib.php)
- `amd/src/nomination.js` — confirmation modal wired to the nomination submit button
- `amd/src/start_load.js` — progress spinner overlay
- `lib.php` injects inline JS/CSS for the nomination form widget (`makeWidget` — a custom searchable chip-based `<select>` replacement) and the action-success overlay
- Pre-built minified files live in `amd/build/`; rebuild with grunt when `amd/src/` changes

### Notifications

`api::send_configured_notification()` handles both channels:
1. **Email** via Moodle's `email_to_user()`, using templates stored in plugin config (subject/body keys editable via `email_templates.php`)
2. **Zoho Cliq** via HTTP POST to configured bot URL — separate `cliq_*` template keys

Template placeholders use `{{key}}` syntax rendered by `api::render_notification_template()`. Available variables include `{{course}}`, `{{mentor}}`, `{{programmanager}}`, `{{award_summary_html}}`, `{{url}}`, `{{recipient_name}}`, etc.

### Certificate generation

Certificates use the `mod_certificatebeautiful` plugin (must be installed separately). `api` handles:
- Fetching the selected template model from Beautiful Certificate
- Rendering Mustache placeholders (`{{spotaward.student_name}}` etc.) into the template HTML
- Processing CSS: converts base64 data URIs and pluginfile URLs to temp files for mPDF compatibility, strips woff/woff2 fonts (mPDF supports only TTF/OTF)
- Generating PDF via mPDF
- Caching background images in `spotaward_cert_backgrounds`

Certificate field placeholders available in templates: see `classes/local/cert_field_map.php`.

### Course filtering

The nomination course picker only shows courses whose shortname starts with one of the configured prefixes (default list in `constants::default_nomination_course_shortname_prefixes()`; overridable in admin settings). Award categories vary by course: Advance C courses (`ADVC102`) get the full set including "Mid C" variants; all others get `standard_award_categories()`.
