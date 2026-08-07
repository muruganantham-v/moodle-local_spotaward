# Spot Award Plugin Change Log

Baseline compared:
- Old plugin: `C:\Users\Admin\Desktop\Spotaward\old spotaward\moodle-local_spotaward`
- Current plugin: `C:\Users\Admin\Pictures\MoodleWindowsInstaller-latest-500\server\moodle\local\spotaward`

## Version comparison

| Item | Old plugin | Current plugin |
| --- | --- | --- |
| Component | `local_spotaward` | `local_spotaward` |
| Version | `2026052704` | `2026070101` |
| Release | `1.1.0` | `1.1.0` |

## High-level summary

The current plugin is a significantly extended version of the older Spot Award plugin. The biggest areas of change are:

- workflow expansion for Program Manager, SS Team, Admin, and Nominator roles
- dynamic reporting based on real gradebook categories
- email and Zoho Cliq template management
- certificate generation, viewing, download, sharing, and admin handover improvements
- access-control tightening for detailed submission/export screens
- performance improvements in dashboard and certificate merge flows
- audit log viewing, filtering, and deletion

## New files added compared to the old plugin

- `audit.php`
- `classes/local/pr_field_map.php`
- `download_csv.php`
- `download_details.php`
- `download_pr.php`
- `forms/bulk_rejection_form.php`
- `forms/reassign_nomination_form.php`
- `fonts/Poppins-Bold.ttf`
- `fonts/Poppins-BoldItalic.ttf`
- `fonts/Poppins-Italic.ttf`
- `fonts/Poppins-Regular.ttf`
- `pix/emertxe_logo.png`
- `Spot_Award_Notification_Templates.pdf`

## Existing files changed

- `ajax.php`
- `amd/build/nomination.min.js`
- `amd/build/table_tools.min.js`
- `amd/src/nomination.js`
- `amd/src/table_tools.js`
- `classes/local/api.php`
- `classes/local/constants.php`
- `classes/output/renderer.php`
- `db/install.xml`
- `db/upgrade.php`
- `email_templates.php`
- `forms/close_record_form.php`
- `forms/closure_form.php`
- `forms/email_templates_form.php`
- `forms/nomination_form.php`
- `forms/share_admin_form.php`
- `index.php`
- `lang/en/local_spotaward.php`
- `lib.php`
- `report.php`
- `settings.php`
- `share_admin.php`
- `styles.css`
- `submission.php`
- `version.php`
- `view_certificate.php`

## Database and upgrade changes

### `db/install.xml`

Added fields in `spotaward_nominations`:
- `admindownloadedtime`
- `admindownloadedby`

Purpose:
- track when Admin downloads certificate bundles
- track which Admin user downloaded them

### `db/upgrade.php`

Upgrade step added for version `2026070101`:
- adds `admindownloadedtime`
- adds `admindownloadedby`
- removes obsolete config `admin_team_members`

## Settings and configuration changes

### `settings.php`

Changes include:
- role-based configuration reorganized
- `Admin role` now appears together with the other workflow roles
- nomination course shortname prefixes are configurable
- Zoho Cliq bot URL and API key settings are supported
- certificate template and PR template selection are available
- links added for:
  - email/Cliq template configuration
  - audit log page
  - certificate template management

## Workflow and role behavior changes

### Nominator flow

Changes:
- stronger draft handling and AJAX autosave
- better nomination form behavior
- history view retained, but detailed submission/export access restricted

### Program Manager flow

Changes:
- partial review tracking
- bulk approval support
- improved dashboard counts and statuses
- reassignment restrictions after review starts

### SS Team flow

Changes:
- certificate generation and sharing actions
- PR document generation/download
- share-to-admin flow
- bulk certificate regenerate/share actions
- reassignment restrictions after admin handover/closure stages

### Admin flow

Changes:
- admin dashboard for admin-shared records
- admin download tracking
- admin certificate bundle downloads

### Manager flow

Changes:
- reporting access and dashboard/report visibility improvements
- nomination detail access left intentionally supported in current workflow

## Reporting changes

### `report.php`, `lib.php`, `classes/local/api.php`

Major reporting changes:
- report filtering now uses real gradebook categories
- report rows show category label and activity type separately
- summary rows are grouped by gradebook category
- student report and course report were aligned with category-based reporting
- stale fixed-category summary assumptions were reduced

## Notification and template changes

### `email_templates.php`, `forms/email_templates_form.php`, `lang/en/local_spotaward.php`

Changes include:
- configurable email templates
- configurable Zoho Cliq templates
- grouped template sections by workflow stage
- richer placeholder support, including `{{moodle_link}}`
- default templates updated with:
  - award summary data
  - record link
  - logo support for email templates

### `classes/local/api.php`

Notification engine changes:
- email rendering improved
- Emertxe logo auto-injected into email HTML if missing
- Cliq templates now enforce record-link inclusion
- additional notification path for admin-share team updates

## Certificate and PR changes

### Certificate handling

Changes include:
- individual certificate generation and storage by nomination item
- inline certificate viewing
- direct certificate download
- merged view/download flows
- ZIP download for all certificates
- sharing certificates to students by email
- selected certificate regenerate/share actions

### Performance improvements

Changes include:
- merged certificate flows moved toward temp-file based processing
- reduced memory pressure for large PDF merge operations

### PR/Admin handover

Changes include:
- PR document download page
- share-to-admin page
- admin attachment bundle creation
- fallback compact certificate bundle generation when size is too large

## Access control and security-related changes

Changes include:
- detailed submission/export screens separated from general nomination-history visibility
- nominators blocked from detailed review/export workflow after submission
- audit actions restricted to site config access
- several workflow screens now use tighter role-specific access checks

## Audit and operational changes

### `audit.php`, `classes/local/api.php`

Changes include:
- audit log listing
- actor/status/date filters
- audit log counting helpers
- delete selected audit rows
- delete all audit logs

## UI and frontend changes

### JavaScript

Changes include:
- nomination form enhancements
- bulk-selection improvements
- report modal behavior
- table tools and export controls
- submission popup icon fix

### CSS / assets

Changes include:
- expanded UI styling in `styles.css`
- bundled Poppins font files
- Emertxe logo asset

## Component-level impact summary

### Core business logic
- `classes/local/api.php` saw the largest change set
- major additions in workflow, notification, report, audit, and certificate behavior

### Forms
- new forms added for bulk rejection and reassignment
- existing forms updated for admin handover and template management

### Pages
- new dedicated pages added for audit, PR download, CSV details, and PDF details
- existing dashboard/submission/certificate pages were expanded substantially

### Language strings
- large increase in workflow, template, reporting, audit, and UI strings

## Important compatibility note

The current plugin is not a small patch over the old version. It includes:
- schema changes
- new pages
- new workflow actions
- new role behaviors
- new assets
- new reporting logic

So deployment from the old plugin to the current plugin should always include:
- plugin code replacement
- Moodle upgrade run
- cache purge
- workflow regression testing for Nominator, PM, SS Team, Manager, and Admin
