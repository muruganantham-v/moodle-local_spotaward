# local_spotaward

A Moodle local plugin that manages a **Spot Award nomination workflow** — mentors nominate students, program managers review them, and the SS Team processes approvals and distributes certificates.

**Version:** 1.1.0 (BETA)  
**Requires:** Moodle 4.0+ (plugin version `2022041900`)  
**Component:** `local_spotaward`

---

## Requirements

- Moodle 4.0 or later
- [`mod_certificatebeautiful`](https://moodle.org/plugins/mod_certificatebeautiful) installed and configured (required for certificate generation)
- mPDF (bundled with Moodle's libraries)
- Zoho Cliq bot URL (optional — for team notifications)

---

## Installation

1. Copy or clone this repository into your Moodle installation:
   ```bash
   cp -r local_spotaward <moodle_root>/local/spotaward
   ```
2. Log in as a Moodle site administrator and complete the upgrade:
   ```
   Site administration → Notifications
   ```
   Or via CLI:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```
3. Configure the plugin at **Site administration → Plugins → Local plugins → Spot Award**.

---

## Configuration

All settings are in **Site administration → Plugins → Local plugins → Spot Award**:

| Setting | Description |
|---------|-------------|
| Nominator role | Role shortname allowed to submit nominations |
| Program Manager role | Role shortname for reviewing nominations |
| SS Team role | Role shortname for processing approved nominations |
| Manager role | Role shortname for viewing reports |
| Allowed course prefixes | Comma-separated shortname prefixes shown in the nomination course picker |
| Admin team members | User IDs notified when a PR document is shared |
| Zoho Cliq bot URL | Endpoint for team notifications (leave blank to disable) |
| Certificate template | `mod_certificatebeautiful` template used for generated certificates |

---

## Nomination workflow

```
pending → (PM approves all students) → approved → (SS Team acts) → ssteamprogress → closed
        ↘ (PM rejects)               → rejected
```

- A nomination stays `pending` until the Program Manager has reviewed every student.
- Once all students are approved the nomination advances to `approved`.
- The SS Team then processes it (`ssteamprogress`) and closes it (`closed`), generating and distributing certificates.
- Every status transition is recorded in `spotaward_status_track` for audit purposes.

---

## Roles and capabilities

| Role | Capability | What they can do |
|------|-----------|-----------------|
| Nominator / Mentor | `local/spotaward:nominate` | Submit nominations via `index.php` |
| Program Manager | `local/spotaward:review` | Approve or reject individual students in `submission.php` |
| SS Team / MAAC Exec | `local/spotaward:sstask` | Process approvals, generate certificates, share PR docs |
| Manager | `local/spotaward:viewreports` | View the reporting dashboard in `report.php` |

Roles are matched by shortname using the values configured in plugin settings.

---

## Pages

| URL | Access | Description |
|-----|--------|-------------|
| `local/spotaward/index.php` | Nominators | Nomination form and submission |
| `local/spotaward/submission.php?id=<id>` | PM / SS Team | Nomination detail, review, certificate actions |
| `local/spotaward/report.php` | Managers | Reporting dashboard |
| `local/spotaward/close_record.php` | SS Team | Close a nomination record |
| `local/spotaward/share_admin.php` | SS Team | Upload PR doc and notify admin team |
| `local/spotaward/email_templates.php` | Admin | Edit email and Cliq notification templates |
| `local/spotaward/view_certificate.php` | Authorised users | View or download a student certificate |
| `local/spotaward/ajax.php` | AJAX | Student report modal content and async actions |

---

## Notifications

Every workflow event triggers a configurable notification. Templates are editable at `email_templates.php` using `{{placeholder}}` syntax.

Available placeholders: `{{course}}`, `{{mentor}}`, `{{programmanager}}`, `{{award_summary_html}}`, `{{url}}`, `{{recipient_name}}`, and more.

Two channels are supported:
- **Email** — via Moodle's `email_to_user()`
- **Zoho Cliq** — HTTP POST to the configured bot URL

---

## Certificate generation

Certificates are generated as PDFs using `mod_certificatebeautiful` templates and mPDF.

Template placeholders follow the pattern `{{spotaward.field_name}}` (e.g. `{{spotaward.student_name}}`, `{{spotaward.roll_no}}`). The full field map is defined in `classes/local/cert_field_map.php`.

Award categories vary by course type:
- **Advance C courses** (`ADVC102`) — full category set including "Mid C" variants
- **All other courses** — standard award categories

---

## Multi-server deployment

For clusters, a deploy script is provided:

```bash
cp scripts/deploy_cluster.env.example scripts/deploy_cluster.env
# Edit deploy_cluster.env with your server list, SSH user, and Moodle path
./scripts/deploy_cluster.sh --config ./scripts/deploy_cluster.env
```

Options:
- `--with-maintenance` — enable Moodle maintenance mode during deploy
- `--dry-run` — preview actions without making changes

`scripts/deploy_cluster.env` is git-ignored and must never be committed (it contains server credentials).

---

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for full development guidelines.

Quick reference:

```bash
# Rebuild AMD modules (from Moodle root)
grunt amd --root=local/spotaward

# Apply DB schema changes
php admin/cli/upgrade.php --non-interactive

# Purge caches after template, JS, or CSS changes
php admin/cli/purge_caches.php
```

All business logic lives in `classes/local/api.php`. Page files handle only input, delegation to `api`, and rendering.

---

## Database tables

| Table | Description |
|-------|-------------|
| `spotaward_nominations` | One row per nomination batch |
| `spotaward_nomination_items` | One row per student per nomination |
| `spotaward_status_track` | Audit trail of every status transition |
| `spotaward_cert_backgrounds` | Cached background images from certificate templates |

---

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html), in line with Moodle's licensing.
