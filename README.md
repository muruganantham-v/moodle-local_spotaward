# local_spotaward

A Moodle local plugin that manages an end-to-end **Spot Award nomination and certificate workflow** — mentors nominate outstanding students, program managers review and approve/reject nominations, SS Team processes approved awards and generates/shares certificates, and managers view aggregate performance reports.

**Version:** 1.1.1 (BETA)  
**Requires:** Moodle 4.0+ (plugin version `2022041900`, component version `2026081901`)  
**Component:** `local_spotaward`  
**Dependencies:** `mod_certificatebeautiful` (>= `2026042700`)

---

## Requirements

- **Moodle 4.0** or later
- [`mod_certificatebeautiful`](https://moodle.org/plugins/mod_certificatebeautiful) installed and configured (required for certificate template generation)
- **mPDF** (bundled with Moodle core)
- **Zoho Cliq bot URL / API Key** (optional — for automated channel notifications)

---

## Installation

1. Copy or clone this repository into your Moodle installation:
   ```bash
   cp -r local_spotaward <moodle_root>/local/spotaward
   ```
2. Log in as a Moodle site administrator and complete the database upgrade:
   ```
   Site administration → Notifications
   ```
   Or via the Moodle CLI:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```
3. Configure the plugin settings at **Site administration → Plugins → Local plugins → Spot Award**.

---

## Configuration

All plugin settings are located at **Site administration → Plugins → Local plugins → Spot Award**:

| Setting | Configuration Key | Description |
|---------|-------------------|-------------|
| **Show in top menu** | `local_spotaward/menu` | Display the Spot Award link in the top site navigation bar |
| **Nominator role** | `local_spotaward/nominator_role` | Role shortname allowed to create and submit student nominations (default: `nominators`) |
| **Program Manager role** | `local_spotaward/program_manager_role` | Role shortname for reviewing and approving/rejecting nominations (default: `programmanagers`) |
| **Admin role** | `local_spotaward/admin_role` | Role shortname for administrative oversight (default: `admin`) |
| **SS Team role** | `local_spotaward/ss_team_role` | Role shortname for processing approved nominations, certificate distribution, and PR docs (default: `ssteam`) |
| **Manager role** | `local_spotaward/manager_role` | Role shortname for viewing aggregated reports and course performance insights (default: `manager`) |
| **Student role** | `local_spotaward/student_role` | Role shortname for nominated students (default: `student`) |
| **Nomination course shortnames** | `local_spotaward/nomination_course_shortnames` | Comma/line-separated course shortname prefixes shown in the nomination course picker |
| **Zoho Cliq bot URL** | `local_spotaward/zohocliq_bot_url` | Endpoint for sending Zoho Cliq channel notifications |
| **Zoho Cliq API key** | `local_spotaward/zohocliq_api_key` | Masked authentication token for Zoho Cliq bot |
| **Certificate template** | `local_spotaward/certificate_templateid` | Selected `mod_certificatebeautiful` model template for generated student certificates |
| **Signature font** | `local_spotaward/signature_font` | Script/signature font used for digital certificate approvals (e.g., Autography) |
| **PR template** | `local_spotaward/pr_templateid` | Template used for generating Purchase Requisition (PR) PDF documents |
| **Email & Cliq Templates** | — | Direct link to edit notification templates in `email_templates.php` |
| **Audit Log** | — | Direct link to view full status history in `audit.php` |
| **Manage Templates** | — | Link to `mod_certificatebeautiful` template model manager |

---

## Nomination Workflow & Status Lifecycle

```
[Draft / Auto-saved]
        ↓
[Pending Review] ──────────→ [Partially Reviewed (X/Y)]
        │                                  │
        │ (PM reviews all students)        │ (PM finishes remaining students)
        ↓                                  ↓
┌───────────────────────┐          ┌───────────────────────┐
│ At least 1 approved   │          │ All students rejected │
│  → ssteamprogress     │          │  → rejected           │
└───────────┬───────────┘          └───────────────────────┘
            │
            │ (SS Team generates certs, shares PR doc, sends certs to students)
            ↓
       [closed]
```

### Status Descriptions:
- **`pending`**: Mentor has submitted the nomination batch; awaiting Program Manager review.
- **`Partially Reviewed (X/Y)`**: Program Manager has started reviewing students (e.g. 2 out of 19 approved/rejected). Nomination remains active for review.
- **`ssteamprogress`** *(Approved — Awaiting SS Team / SS - In Progress)*: Program Manager has completed review, and at least one student is approved. Certificates are automatically generated.
- **`rejected`**: All nominated students in the batch have been rejected with recorded reasons.
- **`closed`**: SS Team has distributed certificates, shared PR with Admin, and closed the batch.

> [!NOTE]
> Program Managers can also **re-approve** previously rejected students while the nomination is under active review.

---

## Roles and Capabilities

| Capability | Archetypes | Description |
|------------|------------|-------------|
| `local/spotaward:nominate` | Teacher, Editing Teacher | Submit student nominations, auto-save drafts, view submission history, download CSV |
| `local/spotaward:review` | Manager | Review nomination batches, approve/reject students individually or in bulk, re-approve items |
| `local/spotaward:sstask` | SS Team, MAAC Executive | Process approved batches, regenerate/download certificates, share certificates to students, share PR docs, close tickets |
| `local/spotaward:viewreports` | Manager | View course-level and student performance reports and aggregated metrics |
| `local/spotaward:managetemplate` | Manager | Manage certificate layout models and template configurations |
| `local/spotaward:downloadcert` | Authorised Users | Download single or bulk certificate PDFs |
| `local/spotaward:viewcert` | Authorised Users | Preview student certificates in browser |
| `local/spotaward:administer` | Administrator | Full administrative privileges, audit log access, template editor, system settings |

---

## Pages and Endpoints

| URL | Access | Description |
|-----|--------|-------------|
| `local/spotaward/index.php` | All configured roles | Role-aware landing hub (Nominator draft & history, PM dashboard, SS Team dashboard, MAAC Exec dashboard, Manager aggregate report) |
| `local/spotaward/submission.php?id=<id>` | PM / SS Team / MAAC | Comprehensive nomination detail page: student items, individual & bulk approval/rejection, certificate downloads, admin sharing, reassignments |
| `local/spotaward/report.php` | Managers / Admins | Performance report showing activity details, category summaries, top performers, and student progress metrics |
| `local/spotaward/audit.php` | Admins / Managers | Full audit trail displaying all historical status changes, actor IDs, reasons, and timestamps |
| `local/spotaward/download_pr.php?nominationid=<id>` | SS Team / Admin | Generates and downloads Purchase Requisition (PR) document PDF |
| `local/spotaward/download_csv.php?id=<id>` | Nominators / PM / SS Team | Exports student nomination list as Excel-compatible UTF-8 CSV |
| `local/spotaward/download_details.php` | SS Team / Admin | Downloads student-level award detail export |
| `local/spotaward/share_admin.php?id=<id>` | SS Team / MAAC | Attaches PR document and shares nomination summary to configured Admin team |
| `local/spotaward/close_record.php` | SS Team | Closes rejected student tickets or batches with closure date and justification |
| `local/spotaward/view_certificate.php` | Authorised users | View or download individual student certificate PDF or combined batch PDF |
| `local/spotaward/email_templates.php` | Admins | Configuration interface for email subjects, email bodies, and Zoho Cliq notification templates |
| `local/spotaward/ajax.php` | Logged-in users | AJAX endpoints for auto-saving drafts and loading student performance modal reports |

---

## Notifications & Placeholders

The plugin supports multi-channel notifications (Email via Moodle's `email_to_user()` and Zoho Cliq via HTTP POST) for every major workflow milestone:
- Mentor submits nomination → PM & Nominator notified
- PM approves/rejects nomination → Mentor, PM, and SS Team notified
- SS Team shares PR doc → Admin team notified
- Certificates shared → Approved students receive certificates via email

### Available Template Placeholders:
| Placeholder | Description |
|-------------|-------------|
| `{{course}}` | Full name of the course |
| `{{mentor}}` | Full name of the nominating mentor |
| `{{programmanager}}` | Full name of the reviewing Program Manager |
| `{{maacexecutive}}` | Full name of the assigned MAAC Executive |
| `{{student_name}}` | Name of the student (for student-level notifications) |
| `{{awardcategory}}` | Award category name |
| `{{award_summary_html}}` | Formatted HTML table summarizing approved/nominated students |
| `{{recipient_name}}` | Name of the message recipient |
| `{{url}}` | Direct URL to the nomination submission page |
| `{{submissiondate}}` | Date the nomination was submitted |

---

## Certificate & PR Placeholders

Certificates and PR documents are dynamically generated via `mod_certificatebeautiful` and mPDF. Placeholders can be used in templates using `{field_key}`, `{$SPOTAWARD->field_key}`, or `{{spotaward.field_key}}`:

| Field Key | Sample Description |
|-----------|--------------------|
| `student_name` | Student's full name |
| `roll_no` / `admission_id` | Student admission/registration username |
| `email` | Student email address |
| `course_name` | Full course title |
| `module_name` | Module name (e.g. Advanced C, Linux Systems) |
| `award_category` | Category of award (e.g. Star Performer, Best Project) |
| `award_description` | Detailed award citation or justification |
| `mentor_name` | Nominating mentor's name |
| `pm_name` | Reviewing Program Manager's name |
| `maac_name` | MAAC Executive's name |
| `date_issued` | Date certificate was generated/closed |
| `certificate_code` | Unique certificate verification code |

---

## Database Tables

| Table | Description |
|-------|-------------|
| `spotaward_nominations` | Master record for each nomination submission (nominator, PM, MAAC exec, course, module, overall status, timestamps) |
| `spotaward_nomination_items` | Student-level award entries (student ID, category, description, status, rejection reason, closure date, reviewer) |
| `spotaward_status_track` | Immutable audit trail recording every status change (`fromstatus` → `tostatus`, `actorid`, `reason`, `timecreated`) |

---

## Multi-Server Deployment

A cluster deployment script is included in `scripts/`:

```bash
cp scripts/deploy_cluster.env.example scripts/deploy_cluster.env
# Configure deploy_cluster.env with server list, SSH user, and Moodle web paths
./scripts/deploy_cluster.sh --config ./scripts/deploy_cluster.env
```

Options:
- `--with-maintenance` — Temporarily enable Moodle maintenance mode during deployment
- `--dry-run` — Preview deployment actions without executing remote file copies

---

## Development

- All business logic is encapsulated in [`classes/local/api.php`](classes/local/api.php).
- UI and data tables are rendered using reusable helpers in [`lib.php`](lib.php).
- Frontend modules in `amd/src/` are vanilla JavaScript (AMD):
  ```bash
  # Rebuild minified AMD modules
  grunt amd --root=local/spotaward

  # Run database upgrades
  php admin/cli/upgrade.php --non-interactive

  # Purge Moodle caches
  php admin/cli/purge_caches.php
  ```

---

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).
