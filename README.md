# local_spotaward — Spot Award System for Moodle

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.0%2B-orange.svg)](https://moodle.org)
[![Version](https://img.shields.io/badge/version-1.1.1%20(BETA)-blue.svg)](version.php)
[![License](https://img.shields.io/badge/license-GPLv3%2B-green.svg)](http://www.gnu.org/copyleft/gpl.html)

**`local_spotaward`** is an enterprise-grade Moodle local plugin that manages the entire lifecycle of student Spot Awards. It connects **Mentors**, **Program Managers**, **MAAC Executives**, the **Student Success (SS) Team**, and **Administrators** in a unified, automated, and auditable nomination-to-certificate pipeline.

---

## Table of Contents

1. [Key Features](#key-features)
2. [Architecture & System Overview](#architecture--system-overview)
3. [System Requirements & Dependencies](#system-requirements--dependencies)
4. [Installation & Upgrade](#installation--upgrade)
5. [Configuration & Settings](#configuration--settings)
6. [Nomination & Review Workflow](#nomination--review-workflow)
7. [Roles & Capabilities](#roles--capabilities)
8. [Pages & URL Routing](#pages--url-routing)
9. [Notification System & Placeholders](#notification-system--placeholders)
10. [Certificate & PR Generation](#certificate--pr-generation)
11. [Database Schema & Architecture](#database-schema--architecture)
12. [Privacy & GDPR Compliance](#privacy--gdpr-compliance)
13. [Backup & Restore Subsystem](#backup--restore-subsystem)
14. [Event Observers & Lifecycle Cleanup](#event-observers--lifecycle-cleanup)
15. [Frontend & UI Components](#frontend--ui-components)
16. [Multi-Server Cluster Deployment](#multi-server-cluster-deployment)
17. [Development & Contributing](#development--contributing)
18. [License](#license)

---

## Key Features

- **Multi-Role Dashboards**: Role-aware interfaces for Mentors/Nominators, Program Managers, MAAC Executives, SS Team, and Site Administrators.
- **Interactive Nomination Form**: Fast course selection with prefix filtering, chip-based searchable student picker, category auto-assignment, draft preview, and auto-save draft functionality.
- **Granular Review & Approval Pipeline**: Program Managers can approve or reject students individually or in bulk, with mandatory rejection reasons and re-approval support.
- **Real-Time Partial Progress Tracking**: Nominations display exact review progress badges (e.g., `Partially Reviewed (2/19)`) across dashboards and detail pages.
- **Dynamic PDF Certificate Generation**: Deep integration with `mod_certificatebeautiful` and mPDF to generate certificates with custom signature fonts, metadata placeholders, and auto-generated verification codes.
- **Purchase Requisition (PR) Documents**: Automatic generation of styled PR documents for approved nominations with handover workflow to the Admin team.
- **Direct Student Distribution**: SS Team can email PDF certificates directly to approved students with a single click or in bulk.
- **Multi-Channel Notifications**: Configurable email and Zoho Cliq bot notifications for all 7 major workflow events with customizable Mustache templates.
- **Comprehensive Audit Trail**: Immutable logging of every status transition (`spotaward_status_track`), capturing timestamp, actor, previous status, next status, and justification reason.
- **Student Performance Reports**: Detailed analytics displaying module scores, completion rates, category distribution insights, and top performers.
- **Enterprise Standards**: Full GDPR privacy provider implementation, Moodle Backup/Restore 2.0 support, event-driven cleanup on course deletion, and multi-server deployment scripts.

---

## Architecture & System Overview

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                    local_spotaward                                      │
├─────────────────┬─────────────────┬──────────────────┬─────────────────┬────────────────┤
│    Mentors      │ Program Manager │ SS Team / MAAC   │      Admin      │    Students    │
│  (Nominators)   │    (Review)     │  (Certificates)  │   (Handover)    │  (Recipients)  │
└────────┬────────┴────────┬────────┴────────┬─────────┴────────┬────────┴────────┬───────┘
         │                 │                 │                  │                 │
 1. Submit Batch           │                 │                  │                 │
    (Auto-draft)           │                 │                  │                 │
         ├─────────────────►                 │                  │                 │
         │          2. Review / Reject       │                  │                 │
         │             (Partial: 2/19)       │                  │                 │
         │                 ├─────────────────►                  │                 │
         │                 │          3. Generate Certs         │                 │
         │                 │             & PR Document          │                 │
         │                 │                 ├──────────────────►                 │
         │                 │                 │           4. Handover to Admin     │
         │                 │                 │                  │                 │
         │                 │                 ├────────────────────────────────────►
         │                 │                 │      5. Email Certs to Students    │
         │                 │                 │                  │                 │
         ▼                 ▼                 ▼                  ▼                 ▼
 ┌───────────────────────────────────────────────────────────────────────────────────────┐
 │                                classes/local/api.php                                  │
 │         (Core Business Logic, Access Control, Notification Engine, mPDF Engine)       │
 └───────────────────────────────────────────────────┬───────────────────────────────────┘
                                                     │
         ┌───────────────────────────────────────────┼───────────────────────────────────┐
         ▼                                           ▼                                   ▼
┌──────────────────┐                       ┌───────────────────┐               ┌──────────────────┐
│ Database Tables  │                       │   Integrations    │               │  Audit & Privacy │
│ - nominations    │                       │ - Beautiful Cert  │               │ - status_track   │
│ - items (student)│                       │ - Zoho Cliq Bot   │               │ - Privacy API    │
│ - status_track   │                       │ - Moodle Mailer   │               │ - Backup/Restore │
└──────────────────┘                       └───────────────────┘               └──────────────────┘
```

---

## System Requirements & Dependencies

| Requirement | Supported Version | Notes |
|---|---|---|
| **Moodle Core** | 4.0+ (Build `2022041900`+) | PHP 8.0, 8.1, or 8.2 supported |
| **`mod_certificatebeautiful`** | `2026042700`+ | Required for certificate templates and background management |
| **mPDF** | Bundled with Moodle | Used for high-fidelity PDF rendering and custom fonts |
| **Zoho Cliq Bot** | Optional Webhook API | Used for team channel alerts |

---

## Installation & Upgrade

### Standard Installation
1. Place the plugin code into your Moodle installation at `<moodle_root>/local/spotaward`:
   ```bash
   git clone https://github.com/muruganantham-v/moodle-local_spotaward.git local/spotaward
   ```
2. Navigate to **Site administration → Notifications** in your browser, or run the CLI upgrade script:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```
3. Purge Moodle caches:
   ```bash
   php admin/cli/purge_caches.php
   ```

---

## Configuration & Settings

Configure the plugin at **Site administration → Plugins → Local plugins → Spot Award**:

| Setting | Config Key | Default | Description |
|---|---|---|---|
| **Show in Top Menu** | `local_spotaward/menu` | `1` (Enabled) | Displays a direct Spot Award link in the main navigation menu |
| **Nominator Role** | `local_spotaward/nominator_role` | `nominators` | Role shortname allowed to submit nominations |
| **Program Manager Role** | `local_spotaward/program_manager_role` | `programmanagers` | Role shortname allowed to review and approve/reject nominations |
| **Admin Role** | `local_spotaward/admin_role` | `admin` | Role shortname for handover notification recipients |
| **SS Team Role** | `local_spotaward/ss_team_role` | `ssteam` | Role shortname for certificate processing, sharing, and closure |
| **Manager Role** | `local_spotaward/manager_role` | `manager` | Role shortname for viewing aggregated reports |
| **Student Role** | `local_spotaward/student_role` | `student` | Role shortname for nominated students |
| **Course Shortnames** | `local_spotaward/nomination_course_shortnames` | *(Empty / Default)* | Comma-separated course shortname prefixes shown in the nomination course picker |
| **Zoho Cliq Bot URL** | `local_spotaward/zohocliq_bot_url` | `https://cliq.zoho.com/...` | Webhook URL for posting Cliq bot channel notifications |
| **Zoho Cliq API Key** | `local_spotaward/zohocliq_api_key` | *(Empty)* | Masked authentication token for Zoho Cliq integration |
| **Certificate Template** | `local_spotaward/certificate_templateid` | `0` (None) | `mod_certificatebeautiful` template model used for student certificates |
| **Signature Font** | `local_spotaward/signature_font` | `autography` | Font used for Program Manager & MAAC digital signatures on certificates |
| **PR Template** | `local_spotaward/pr_templateid` | `0` (None) | Template used for Purchase Requisition (PR) PDF generation |
| **Email Templates** | — | — | Direct link to configure email and Cliq notification templates (`email_templates.php`) |
| **Audit Log** | — | — | Direct link to view the system audit trail (`audit.php`) |

---

## Nomination & Review Workflow

```
[ Mentor Draft ] ──(Auto-save / Submit)──► [ Pending Review ]
                                                   │
                         ┌─────────────────────────┴─────────────────────────┐
                         ▼                                                   ▼
             [ Individual Review ]                                   [ Bulk Review ]
                         │                                                   │
                         ├────────────► [ Partially Reviewed (X/Y) ] ◄───────┤
                         │                                                   │
                         └─────────────────────────┬─────────────────────────┘
                                                   │
                        (All students reviewed by Program Manager)
                                                   │
                         ┌─────────────────────────┴─────────────────────────┐
                         │                                                   │
            (At least 1 student approved)                               (All rejected)
                         │                                                   │
                         ▼                                                   ▼
               [ ssteamprogress ]                                     [ rejected ]
       ("Approved — Awaiting SS Team")                                       │
                         │                                                   │ (Optional PM re-approval)
                         ├───────────────────────────────────────────────────┘
                         │
     (SS Team generates certs, shares PR with Admin, emails students)
                         │
                         ▼
                     [ closed ]
```

### Workflow States:
1. **`pending`**: Batch submitted by mentor; awaiting Program Manager review.
2. **`Partially Reviewed (X/Y)`**: Program Manager has reviewed some students in the batch (e.g. 2 approved out of 19). The nomination remains open for review.
3. **`ssteamprogress`** *(Approved — Awaiting SS Team)*: All students reviewed and at least one student was approved. Certificates are automatically generated and ready for SS Team processing.
4. **`rejected`**: All students in the nomination batch were rejected with recorded reasons.
5. **`closed`**: SS Team has distributed certificates, handed over the PR document, and closed the batch.

---

## Roles & Capabilities

All access checks are enforced via Moodle capabilities in [`db/access.php`](db/access.php):

| Capability | Context | Allowed Archetypes | Description |
|---|---|---|---|
| **`local/spotaward:nominate`** | System | Teacher, Editing Teacher | Submit nominations, auto-save drafts, view submission history, download CSV |
| **`local/spotaward:review`** | System | Manager | Review submissions, approve/reject students, bulk review, re-approve items |
| **`local/spotaward:sstask`** | System | *(Assigned via SS Team role)* | Process approvals, generate certificates, share PR docs, email certificates, close tickets |
| **`local/spotaward:viewreports`** | System | Manager | Access aggregate performance and student insight reports |
| **`local/spotaward:managetemplate`**| System | Manager | Configure certificate model layouts |
| **`local/spotaward:downloadcert`** | System | *(Configured roles)* | Download single or bulk certificate PDFs |
| **`local/spotaward:viewcert`** | System | *(Configured roles)* | Preview certificates in browser |
| **`local/spotaward:administer`** | System | Administrator | Full plugin configuration, email template editing, and audit trail inspection |

---

## Pages & URL Routing

| Endpoint | Role Access | Purpose |
|---|---|---|
| **`index.php`** | Nominators, PM, SS Team, Manager | Role-aware dashboard: nomination form, active review queues, history tables, aggregate reports |
| **`submission.php?id=<id>`** | PM, SS Team, MAAC Exec | Nomination detail view: student-level approval/rejection, certificate links, PR actions, reassignments |
| **`report.php`** | Managers, Admins | Course and student performance reports with activity analytics and top performers |
| **`audit.php`** | Admins, Managers | Searchable audit trail of all historical status transitions and reasons |
| **`download_pr.php?nominationid=<id>`** | SS Team, Admin | Dynamic Purchase Requisition (PR) PDF document generation and download |
| **`download_csv.php?id=<id>`** | Nominators, PM, SS Team | Export student nomination records to Excel-compatible UTF-8 CSV |
| **`download_details.php`** | SS Team, Admin | Export student-level award details as PDF |
| **`share_admin.php?id=<id>`** | SS Team, MAAC Exec | Upload/attach PR document and notify configured Admin team |
| **`close_record.php`** | SS Team | Close rejected student tickets or batches with closure date and justification |
| **`view_certificate.php`** | Authorised Users | Preview single certificate, download single PDF, or download combined batch PDF |
| **`email_templates.php`** | Admins | Visual editor for email subject/body and Zoho Cliq bot templates |
| **`ajax.php`** | Authenticated Users | AJAX endpoints for auto-saving drafts and loading student performance modal content |

---

## Notification System & Placeholders

The notification engine supports both **Email** (`email_to_user`) and **Zoho Cliq** (Webhook HTTP POST) across 7 distinct workflow stages:

```
1. Mentor Submits   ──► submission_pm, submission_ss, submission_mentor
2. PM Reviews       ──► pm_to_ss, pm_to_mentor, pm_to_pm
3. Admin Handover   ──► ss_to_admin, admin_share_team
4. Reassignment     ──► reassignment
5. Record Closed    ──► record_closed
6. Certificate Sent ──► student_certificate (with attached PDF)
```

### Template Placeholders:
| Placeholder | Description |
|---|---|
| `{{course}}` | Course full name |
| `{{module}}` | Module name (e.g. Linux Systems, Advanced C) |
| `{{mentor_name}}` / `{{mentor}}` | Nominating mentor's full name |
| `{{program_manager_name}}` / `{{programmanager}}` | Reviewing Program Manager's full name |
| `{{maac_executive_name}}` / `{{maacexecutive}}` | Assigned MAAC Executive's full name |
| `{{student_name}}` | Nominated student's full name |
| `{{student_email}}` | Student email address |
| `{{award_category}}` / `{{awardcategory}}` | Assigned award category |
| `{{award_summary_html}}` | Formatted HTML table summarizing nominated students |
| `{{total_students}}` | Total count of students in the nomination |
| `{{closure_date}}` | Date record was closed |
| `{{moodle_link}}` / `{{url}}` | Direct link to the nomination submission page |
| `{{recipient_name}}` | Recipient user's full name |

---

## Certificate & PR Generation

Certificates and PR documents are rendered using **mPDF** and templates defined in `mod_certificatebeautiful`. Placeholders support standard `{key}`, `{$SPOTAWARD->key}`, and `{{spotaward.key}}` formats:

### Supported Field Placeholders:
| Placeholder Key | Replaced Value |
|---|---|
| `student_name` | Student's full name |
| `roll_no` / `admission_id` | Student username / admission number |
| `email` | Student email address |
| `course_name` | Course full name |
| `module_name` | Module name |
| `award_category` | Award category |
| `award_description` | Award justification description |
| `mentor_name` | Mentor full name |
| `pm_name` | Program Manager full name |
| `maac_name` | MAAC Executive full name |
| `date_issued` | Formatted issue date |
| `certificate_code` | Unique certificate verification code |

---

## Database Schema & Architecture

Defined in [`db/install.xml`](db/install.xml):

```
┌─────────────────────────────────────────────────────────┐
│                 spotaward_nominations                   │
├─────────────────────────────────────────────────────────┤
│ id (PK)                                                 │
│ nominatorid, programmanagerid, maacexecutiveid          │
│ courseid, modulename, awardcategory, professional       │
│ studentcount, status                                    │
│ adminsharedtime, adminsharedby                          │
│ admindownloadedtime, admindownloadedby                  │
│ timecreated, timemodified                               │
└───────────────────────────┬─────────────────────────────┘
                            │ 1:N
                            ▼
┌─────────────────────────────────────────────────────────┐
│               spotaward_nomination_items                │
├─────────────────────────────────────────────────────────┤
│ id (PK)                                                 │
│ nominationid (FK) ──────────────────────────────────────┘
│ studentid, awardcategory, professional, awarddescription
│ status (pending | ssteamprogress | rejected | closed)   │
│ rejectionreason, closuredate                            │
│ reviewedby, timereviewed                                │
└───────────────────────────┬─────────────────────────────┘
                            │ 1:N
                            ▼
┌─────────────────────────────────────────────────────────┐
│                 spotaward_status_track                  │
├─────────────────────────────────────────────────────────┤
│ id (PK)                                                 │
│ nominationid (FK)                                       │
│ nominationitemid (FK)                                   │
│ actorid, fromstatus, tostatus, reason, timecreated      │
└─────────────────────────────────────────────────────────┘
```

---

## Privacy & GDPR Compliance

Implemented in [`classes/privacy/provider.php`](classes/privacy/provider.php):
- **Metadata Reporting**: Discloses all personal data stored in `spotaward_nominations`, `spotaward_nomination_items`, and `spotaward_status_track`.
- **Data Export**: Exports all nominations submitted, reviewed, or received by a user into organized JSON structures and certificate PDF attachments.
- **Context Deletion**: Deletes nomination records and files when a course context is deleted.
- **User Data Deletion**: Anonymizes and cleans up user references upon GDPR user deletion requests.

---

## Backup & Restore Subsystem

Implemented in [`backup/moodle2/`](backup/moodle2/):
- Full support for Moodle's Course Backup and Restore 2.0.
- Preserves nomination batches, student item records, review states, and audit tracking across course backups and migrations.

---

## Event Observers & Lifecycle Cleanup

Implemented in [`db/events.php`](db/events.php) and [`classes/observer.php`](classes/observer.php):
- Listens to `\core\event\course_deleted`.
- Automatically cascades deletion to remove all associated `spotaward_nominations`, `spotaward_nomination_items`, `spotaward_status_track` records, and stored certificate files when a course is deleted.

---

## Frontend & UI Components

- **Vanilla JavaScript (AMD Modules)** in `amd/src/`:
  - `table_tools.js` — Client-side sorting, pagination, multi-column filtering, search, and CSV export.
  - `nomination.js` — Chip-based searchable student selector and submission confirmation dialogs.
  - `start_load.js` — Full-page action spinner and success toast notifications.
- **Custom CSS** in `styles.css`:
  - Modern card layouts, responsive summary stat grids, status badge palettes, and admission ID highlight warnings.

---

## Multi-Server Cluster Deployment

For production Moodle server clusters:

```bash
cp scripts/deploy_cluster.env.example scripts/deploy_cluster.env
# Edit deploy_cluster.env with server list, SSH user, and web root paths
./scripts/deploy_cluster.sh --config ./scripts/deploy_cluster.env
```

Options:
- `--with-maintenance` — Enables Moodle maintenance mode during rollout
- `--dry-run` — Previews file synchronization without modifying servers

---

## Development & Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for development workflows.

```bash
# Rebuild minified JavaScript modules (from Moodle root)
grunt amd --root=local/spotaward

# Run PHPUnit tests
vendor/bin/phpunit --testsuite local_spotaward_testsuite

# Apply database schema updates
php admin/cli/upgrade.php --non-interactive

# Purge caches
php admin/cli/purge_caches.php
```

---

## License

This plugin is licensed under the [GNU General Public License v3.0 or later](http://www.gnu.org/copyleft/gpl.html).
