# Code Review — `feature/spotaward-phase-2`

**Repo:** `EmertxeInfoTech/moodle-local_spotaward`
**Branch:** `feature/spotaward-phase-2` @ `f32607d`
**Base:** `main` @ `c83575f`
**Scope:** 38 commits, 118 files, +10,838 / −1,977
**Reviewed:** 2026-09-01

Merge mechanics are clean — `main` is an ancestor, branch is 0 commits behind, so this fast-forwards. All 41 PHP files lint clean on PHP 8.5.

## Verdict

**Do not merge as-is.** Three issues break production behaviour on day one, and one of them re-introduces a bug that `main` fixed four commits ago. Everything below is reproducible against the branch; each item lists the exact file and line.

| # | Severity | Issue | Location |
|---|----------|-------|----------|
| 1 | **Blocker** | Status precedence regression — completed nominations report as `rejected` + spurious emails | `classes/local/api.php:6362` |
| 2 | **Blocker** | 24 language strings referenced but never defined | `lang/en/local_spotaward.php` |
| 3 | **Blocker** | 2 selectable signature fonts crash PDF generation | `classes/local/constants.php:527` |
| 4 | High | Two undefined variables gate a feature off for Managers | `submission.php:451` |
| 5 | High | Students cannot open their own certificates | `view_certificate.php:45` |
| 6 | High | Nominations marked "downloaded" before the PDF is generated | `classes/local/api.php:4846` |
| 7 | Medium | `copy_content_to()` exception aborts bulk share mid-run | `classes/local/api.php:2378, 2460` |
| 8 | Medium | Submit button can lock permanently when autosave fails | `amd/src/nomination.js:147` |
| 9 | Medium | Background fallback regex can stamp a stray image full-page | `classes/local/api.php:3440` |
| 10 | Medium | Page-scoped stats presented as course-wide | `lib.php:1585` |
| 11 | Low | Dead method calls two methods that do not exist | `classes/local/api.php:6427` |
| 12 | Low | Dead merge helpers, non-unique temp filenames | `classes/local/api.php:3565, 3613` |
| 13 | Low | Verbatim duplicated assignment block | `classes/local/api.php:6744` |

---

## 1. Blocker — status precedence regression

**`classes/local/api.php:6358-6366`, `refresh_nomination_status()`**

The branch swaps the order of the `rejected` and `closed` checks relative to `main`:

```php
// main (correct)
if      ($haspending)        { $newstatus = 'pending'; }
else if ($hasssteamprogress) { $newstatus = 'ssteamprogress'; }
else if ($hasclosed)         { $newstatus = 'closed'; }
else if ($hasrejected)       { $newstatus = 'rejected'; }

// branch (line 6362 — rejected now wins over closed)
if      ($haspending)        { $newstatus = 'pending'; }
else if ($hasssteamprogress) { $newstatus = 'ssteamprogress'; }
else if ($hasrejected)       { $newstatus = 'rejected'; }
else if ($hasclosed)         { $newstatus = 'closed'; }
```

**Failure path.** Reproducible on a nomination with a mix of approved and rejected students:

1. PM rejects 2 of 5 students. Items: `{3 ssteamprogress, 2 rejected}`.
2. SS team closes the record. `close_nomination_record()` skips rejected items (`continue` on `status === 'rejected'`) and sets the nomination to `closed` **directly** — it does not call `refresh_nomination_status()`. Items: `{3 closed, 2 rejected}`. Status is correctly `closed`.
3. SS team closes one of the two remaining rejected tickets. `close_rejected_ticket()` sets that item to `closed` and **does** call `refresh_nomination_status()` (`api.php:6881`). Items: `{4 closed, 1 rejected}`.
4. `$hasrejected` is now checked before `$hasclosed`, so an already-completed and distributed nomination flips from `closed` back to **`rejected`**.

On `main`, step 4 evaluates `$hasclosed` first and correctly keeps `closed`.

**Secondary damage.** Because `$oldstatus !== $newstatus`, the transition block fires `send_program_manager_decision_to_mentor_notification($nominationid, 'rejected')` and its PM counterpart — mailing the mentor and Program Manager a rejection notice for an award that was already distributed.

Any caller of `refresh_nomination_status()` on a closed nomination that still has a rejected item will trigger this: `close_rejected_ticket()` (`api.php:6881`), `update_item_status()` (`api.php:6770`), the bulk review path (`api.php:6821`), and `submission.php:99` (approve-all).

This reverts the intent of `91007ad` ("repair nominations stuck in pending when all items already approved") and `e36dfe6` on `main`. It looks like the branch was developed without those commits in view.

**Fix:** restore `$hasclosed` ahead of `$hasrejected`. Add a regression test covering the mixed `{closed, rejected}` item set.

---

## 2. Blocker — 24 undefined language strings

Every one of these is passed to `get_string()` or `moodle_exception` with component `local_spotaward`, but none exists in `lang/en/local_spotaward.php`. They render as `[[stringname]]` and emit debugging warnings.

| String | Referenced from |
|---|---|
| `accessdenied` | `ajax.php` |
| `accessdeniedcoursecontext` | `ajax.php` |
| `alreadysharedtoadmin` | `share_admin.php`, `classes/local/api.php` |
| `auditlogdeleteallconfirm` | `audit.php` |
| `auditlogdeletealltoken` | `audit.php` |
| `autosaveerror` | `ajax.php` |
| `cannotdeletereviewed` | `classes/local/api.php` |
| `csv_approver` | `download_csv.php` |
| `csv_awardcategory` | `download_csv.php` |
| `csv_comments` | `download_csv.php` |
| `csv_date` | `download_csv.php` |
| `csv_email` | `download_csv.php` |
| `csv_issuedto` | `download_csv.php` |
| `csv_regnid` | `download_csv.php` |
| `csv_slno` | `download_csv.php` |
| `csv_student` | `download_csv.php` |
| `csvdownloadnotallowed` | `download_csv.php` |
| `invalidcourseid` | `ajax.php` |
| `invalidstatustransition` | `classes/local/api.php` |
| `missingmaacexecutive` | `classes/local/api.php` |
| `nominationitems` | `classes/privacy/provider.php` |
| `nominations` | `classes/privacy/provider.php` |
| `privacy:certificatefiles` | `classes/privacy/provider.php` |
| `statustracking` | `classes/privacy/provider.php` |

Three consequences worth calling out specifically:

- **CSV export is corrupted, not just ugly.** `download_csv.php` has already sent its headers and opened the output stream before calling `get_string()` for the 9 column names. With debugging enabled, the debug notice is written *into* the CSV body.
- **The audit delete-all guard is unusable.** `audit.php:101` compares typed input against `get_string('auditlogdeletealltoken', …)`. The comparison still succeeds — the admin just has to literally type `[[auditlogdeletealltoken]]`.
- **GDPR exports get broken path segments.** The privacy provider writes user data under `[[nominations]]`, `[[nominationitems]]`, `[[statustracking]]`.

**Fix:** add all 24. Suggested check to keep in CI — see the appendix.

---

## 3. Blocker — two signature fonts crash certificate generation

**`classes/local/constants.php:527`, `signature_fonts_mapping()`**

The setting offers 22 selectable signature fonts. Two are CFF/PostScript-outline OTFs. mPDF's `TTFontFile` parser reads the `glyf` and `loca` tables to build embedded subsets; CFF-flavoured fonts have neither, so mPDF throws and certificate generation fails.

```
mapped key             file                       sfnt      glyf  loca  CFF
moralana               Moralana DEMO.otf          OTTO      NO    NO    yes   <-- fails
californiansignature   Californian Signature.otf  OTTO      NO    NO    yes   <-- fails
ronthelbrush           Ronthel Brush DEMO.otf     TrueType  yes   yes   NO    ok
(the other 19 mapped fonts are all TrueType — ok)
```

Selecting either in **Site administration → Plugins → Local → Spot Award → Signature font** breaks PDF generation site-wide, for all certificates, not just ones using that font.

Note the pattern already exists in the repo: `Signatie`, `Thesignature` and `Jalliya` each ship both `.otf` and `.ttf`, and the mapping correctly points at the `.ttf`. These two just never got the same treatment.

**Fix:** source TrueType builds of both faces and repoint the mapping, or drop the two options. Longer term, validate the sfnt tag at settings-save time so an unusable font cannot be selected.

See [Context — why `fonts/` exists](#context--why-fonts-exists) for why these two were missed.

---

## Context — why `fonts/` exists

The `fonts/` directory has drawn three separate findings (3, and two under Repository hygiene). They are easier to act on with the feature they belong to in view, so recording it here.

There are **two unrelated font additions** on this branch, with different purposes and different verdicts.

### Poppins — 4 files, commit `c7f483b`, legitimate

The body typeface for the student-details PDF:

```php
// download_details.php:444
'default_font'  => 'poppins',
'fontDir'       => array_merge($defaultfontdirs, [__DIR__ . '/fonts']),
```

These have to be files in the repo. mPDF embeds fonts by reading them off disk — it cannot pull from Google Fonts at render time the way an HTML preview does. Four weights, all four used. **No action needed.**

### The 30 script fonts — commit `4a1b747`, needs pruning

These back a *simulated handwritten signature* feature. Rather than have three people upload scanned signature images, the certificate renders each name in a handwriting font so it reads as signed — `classes/local/cert_field_map.php:185`:

```php
$sigfont = strtolower(constants::signature_font());
$pm_sig_html = '<span style="font-family: \'' . $sigfont . '\';">'
             . s($data['program_manager_signature']) . '</span>';
```

The values being wrapped are plain names (`cert_field_map.php:92-94`):

```php
'program_manager_signature' => fullname($programmanager),
'nominator_signature'       => fullname($nominator),
'ss_team_signature'         => $ssteamname,
```

The admin picks one style site-wide under **Signature font**.

### Why 34 files ship for a feature that uses one

`signature_font` is single-valued — one font is active per site. So on any given install 21 of the 22 offered fonts are unused, and 8 more are not even in the picker.

Commit `4a1b747` shows how this happened: alongside the fonts it added `compare_plugin.html` and `compare_template.html` (side-by-side "what mPDF renders vs. what the design looks like" harnesses) and `_check_aditya_items.php`, a debug script later removed in `8be37de`. That commit is one long "make the certificate look right" session, and the font candidates, the comparison harness and the issue drafts were all committed together.

The leftovers are the trial-and-error record:

| Leftover | What it indicates |
|---|---|
| `Signatie`, `Thesignature`, `Jalliya` ship **both** `.otf` and `.ttf`, mapping points at `.ttf` | The CFF embedding failure was hit, the fonts were converted to TrueType, and both copies were left behind |
| `Havana.ttf`, `Havana-Book.ttf`, `Havana3D.ttf`, `Havana Lorde.ttf` | Four cuts of one family evaluated, one mapped |
| `caveat-regular`, `caveat-bold`, `caveatbrush-regular` | Three evaluated, one mapped |

The 8 orphaned files are not arbitrary — they are the discarded candidates from that same evaluation.

This also connects two findings that otherwise look unrelated:

- **Finding 3** is that conversion job left half-finished. `Moralana DEMO.otf` and `Californian Signature.otf` never got the `.ttf` treatment their neighbours did.
- The **Windows/Arial probe** (`api.php:4290`, under Repository hygiene) sits in the same code path and arrived in the same push — a Windows dev machine leaking into shipped configuration.

### Recommended direction

Shipping fonts in-repo is correct and unavoidable for mPDF; the fix is pruning, not removal.

1. Convert or drop the two CFF fonts (finding 3) — **blocker**.
2. Delete the 8 orphans (~1.5 MB) that appear in no mapping and no code.
3. Decide whether a site needs 22 signature styles or whether 3–4 vetted ones suffice.

Step 3 also resolves the licensing exposure: `Moralana DEMO`, `Ronthel Brush DEMO` and "Havana Personal Use Only" were swept in during a grab-every-candidate pass. A deliberate shortlist would not include demo- or personal-use-licensed faces on an official certificate.

---

## 4. High — undefined variables disable a Manager feature

**`submission.php:451`**

```php
if ($isssteam || $ismanager || is_siteadmin() || $isadmin) {
```

Neither `$ismanager` nor `$isadmin` is ever assigned in this file — line 451 is their only occurrence. The variables actually in scope are `$canmanagerapprove`, `$isssteam`, `$ispm`, `$isnominator`, `$cansharetoadmin`.

Two effects: two `Undefined variable` warnings per page render on PHP 8, and the intended behaviour is silently lost — a Manager reaches this block via `$canviewcertificates = $canmanagerapprove || $isssteam` but never sees the "Share certificates to students" button.

**Fix:** use the variables that exist (`$canmanagerapprove`, and whatever was meant by `$isadmin`).

---

## 5. High — students cannot open their own certificates

**`view_certificate.php:45-50`, with `lib.php:2179`**

`local_spotaward_myprofile_navigation()` adds "Spot awards" nodes to a user's own profile (`$iscurrentuser`), linking to `view_certificate.php`. That page then requires:

```php
$cancertificateaccess = is_siteadmin()
    || api::is_manager($USER->id)
    || api::is_ss_team($USER->id)
    || api::is_assigned_maac_executive($nomination, (int)$USER->id);
if (!$cancertificateaccess) {
    throw new moodle_exception('notauthorised', 'local_spotaward');
}
```

An award recipient is none of those. A student clicking the node on their own profile always gets `notauthorised` — the feature is unreachable for the audience it was built for.

**Fix:** allow the recipient through when `$userid === $USER->id` and the item belongs to them, or drop the profile nodes if student self-service was not intended.

---

## 6. High — records marked delivered before the PDF exists

**`classes/local/api.php:4846-4855`, `download_admin_certificates()`**

```php
$DB->execute(
    "UPDATE {spotaward_nominations}
        SET admindownloadedtime = :admindownloadedtime,
            admindownloadedby   = :admindownloadedby, ...
      WHERE id $insql", $params);          // line 4846

$pdfcontent = self::generate_merged_nominations_certificates_pdf($nominationids);  // line 4855
```

The flag is committed before generation runs. If generation throws — missing or invalid Beautiful Certificate template, mPDF memory exhaustion, `nocertificates` — the admin sees an error page but every selected nomination is now permanently flagged "Downloaded" on the admin dashboard, with no way to reset it from the UI.

**Fix:** move the `UPDATE` after generation succeeds, ideally inside the same transaction as the send.

---

## 7. Medium — bulk share aborts on the first bad file

**`classes/local/api.php:2378` and `:2460`**

The previous implementation used `file_put_contents(...)` and skipped failures:

```php
if (file_put_contents($temppath, $content) === false) {
    continue;
}
```

The replacement calls `stored_file::copy_content_to($temppath)`, which throws `file_exception` rather than returning `false`. Neither loop has a `catch` — only `finally`. One unreadable or undeletable temp path now aborts the entire "share certificates to students" run partway through, leaving the remaining students unmailed and no partial-success report.

**Fix:** wrap the copy in `try/catch (file_exception)`, `continue` on failure, and collect failures for a summary shown to the operator.

---

## 8. Medium — submit button can lock permanently

**`amd/src/nomination.js:147`**

```js
var canSubmit = hasRecoverableState && !dirty;
...
submitBtn.disabled = !canSubmit;   // line 153
```

`handleSaveError()` (line 281) runs on any non-2xx response, unparseable body, or `xhr.onerror`. It sets `dirty = true` and only reschedules the autosave. If `ajax.php?action=autosavedraft` is unreachable — expired session returning 403, proxy error, client offline — the nominator can fill in a complete, valid form and the Submit button stays greyed out with no way to force submission. The same lockout applies when `config.autosaveurl` is empty.

**Fix:** allow submit whenever the form has meaningful content, and surface autosave failure as a visible warning rather than a silent disable.

---

## 9. Medium — background fallback can stretch a stray image full-page

**`classes/local/api.php:3440`, `apply_certificate_background()`**

The old code returned early when no `[data-gjs-type=wrapper]` block carried a background. The new chain ends with a broad fallback:

```php
preg_match('/background(?:-image)?\s*:\s*url\((.*?)\)/is', $combined, $m);
// $combined = $css . ' ' . $html
```

For any template *without* a wrapper background, the first `background: url(...)` anywhere in the CSS or inline HTML — a 60×60 decorative icon, a signature graphic — is handed to `$mpdf->Image($source, 0, 0, 0, 0, ...)` and stretched across the whole page behind the certificate.

**Fix:** keep the early return when no wrapper background is found, or constrain the fallback to rules that actually apply to the wrapper/body selector.

---

## 10. Medium — page-scoped statistics labelled as course-wide

**`report.php:74` with `lib.php:1585`, `local_spotaward_build_course_report_insights()`**

`get_course_report()` now slices `$students` to 25 per page before building the report. The insights function derives "Overall average", "Overall completion", "Top performers", "Needs attention" and the grade-band distribution from `$report['rows']` / `rowsbystudent` — i.e. only the current page.

A 120-student course shows "Top performers" drawn from an arbitrary alphabetical slice of 25, and every number changes as the reader pages through.

**Fix:** compute aggregates from a separate unpaginated query, or relabel them as page-scoped.

---

## 11. Low — dead method calls two methods that do not exist

**`classes/local/api.php:6427`, `optimize_pdf_file_with_ghostscript()`**

Calls `self::find_ghostscript_binary()` (line 6434) and `self::run_pdf_optimization_command($cmd)` (line 6467). Neither is declared anywhere in the class or the repo:

```
find_ghostscript_binary        declared=0  called=1
run_pdf_optimization_command   declared=0  called=1
optimize_pdf_file_with_ghostscript  declared=1  called=0
```

Currently unreferenced, so it is dead code — but it fatals with `Call to undefined method` the moment anyone wires it up.

**Fix:** delete the method, or implement the two helpers.

---

## 12. Low — dead merge helpers with colliding temp filenames

**`classes/local/api.php:3565` and `:3613`**

`merge_stored_pdf_files_to_temp_path()` (3613) is called from nowhere; it calls `merge_pdf_files_to_temp_path()` (3565) at line 3623, which has no other caller. Both are dead.

If adopted as-is, 3613 writes to fixed names `file_0.pdf`, `file_1.pdf`, … inside the shared `make_temp_directory('spotaward_merge_temp')`, so two concurrent merges would overwrite each other's inputs.

**Fix:** remove both, or give them per-request unique paths before use.

---

## 13. Low — verbatim duplicated assignment block

**`classes/local/api.php:6739-6751`, `update_item_status()`**

```php
$item->status = $status;
if ($reason !== null) { $item->rejectionreason = $reason; }
$item->reviewedby = $actorid;
$item->timereviewed = time();      // line 6744

$item->status = $status;           // identical block repeated
if ($reason !== null) { $item->rejectionreason = $reason; }
$item->reviewedby = $actorid;
$item->timereviewed = time();      // line 6751
$DB->update_record('spotaward_nomination_items', $item);
```

Harmless at runtime, but it reads like a bad merge and will confuse the next reader. Looks like an artefact of the lock/transaction refactor.

**Fix:** drop the first copy.

---

## Repository hygiene

**22 development artifacts committed** (~190 KB) that should not ship in a plugin release:

```
major.md, minor.md, moderate.md          an AI code-review report on this codebase
issue_drafts/                            10 draft issue bodies and close notes
push_and_issues.ps1                      see below
compare_plugin.html, compare_template.html   font/layout comparison scratch pages
Spot_Award_Notification_Templates.pdf    112 KB reference doc
```

`push_and_issues.ps1` is worth a second look: it runs `git add . && git commit && git push origin main` and files issues against `muruganantham-v/moodle-local_spotaward` — a *different fork*. No secrets leaked (`$env:GITHUB_TOKEN=""` is empty), but this should not be in the repo. The branch's `.gitignore` addition covers `scratch/` and `.agents/`, which catches none of the above.

**Windows-only font branch changes output by environment** — `classes/local/api.php:4290`:

```php
$windowsfontdir = getenv('WINDIR') ? ... : 'C:\\Windows\\Fonts';
if (is_file($arialregular)) {
    $fontdata['arial'] = [...];
    $defaultfont = 'arial';       // else stays 'dejavusans'
}
```

On a Windows dev machine certificates default to Arial; on the Linux servers `deploy_cluster.sh` targets, they default to DejaVu Sans. Same code, different rendering — which is likely why `compare_plugin.html` was written in the first place. Pin one font that ships with the plugin. This arrived in the same push as the signature fonts — see [Context — why `fonts/` exists](#context--why-fonts-exists).

**~1.5 MB of unreferenced fonts** — 8 of the 34 shipped fonts appear in no mapping and no code: `Havana Lorde.ttf`, `Havana-Book.ttf`, `Havana3D.ttf`, `Jalliya.otf`, `Signatie.otf`, `Thesignature.otf`, `caveat-bold.ttf`, `caveatbrush-regular.ttf`. (The 4 Poppins files *are* used, by `download_details.php`.) Five font filenames also contain spaces, which is fragile across mPDF font registration, rsync and deploy paths. These 8 are the discarded candidates from the signature-font evaluation — see [Context — why `fonts/` exists](#context--why-fonts-exists).

**Font licensing** — `Moralana DEMO.otf`, `Ronthel Brush DEMO.otf`, and the option labelled `Havana Personal Use Only` are demo / personal-use faces being redistributed in a GPLv3 plugin and used to produce official certificates. This is a licensing decision, not a code fix, but it needs an owner. Narrowing the picker to a vetted shortlist resolves it — see [Context — why `fonts/` exists](#context--why-fonts-exists).

**PHPUnit namespace** — `tests/api_test.php` and `tests/privacy_provider_test.php` both declare `namespace local_spotaward\tests;`. Moodle expects the namespace to match the frankenstyle component for files directly under `tests/`, i.e. `namespace local_spotaward;`. Not verified against Moodle core (no install available at review time) — please run the suite once before merging.

---

## Behaviour changes that need explicit sign-off

These look deliberate, but they change operational behaviour in ways that are hard to reverse. Flagging for a decision rather than as defects.

**Deleting a course now destroys its award history.** The new `course_deleted` observer (`classes/observer.php`, registered in `db/events.php`) deletes all `spotaward_nominations`, `spotaward_nomination_items`, `spotaward_status_track` rows and certificate files for that course. Sensible for GDPR; destructive if courses are deleted at end of batch, since it erases the audit trail for awards already issued. The new backup/restore classes mitigate this only if someone actually took a backup first.

**`admin_team_members` is deleted with no migration path.** Upgrade step `2026070101` runs `unset_config('admin_team_members', 'local_spotaward')`. `get_configured_users()` was replaced by `get_admin_users()`, which reads role assignments for the `admin_role` setting — default shortname `admin`, which is **not** a stock Moodle role. On an upgraded site with no such role, `get_admin_users()` returns `[]` and every "Share to Admin" attempt throws `noadminconfigured` until an administrator manually creates the role, points the setting at it, and assigns users. The previously configured recipient emails are gone. This needs either a migration or a release note.

**Wider nominate grant.** Upgrade step `2026081901` assigns `local/spotaward:nominate` to `teacher` and `editingteacher` at system context with `overwrite=true` (mirrored in `db/install.php`, so install and upgrade are consistent). Broader than the four documented roles.

---

## What was checked and is correct

Worth saying explicitly — a lot of this branch is solid:

- **Access control on all four new entry points.** `audit.php`, `download_csv.php`, `download_details.php` and `download_pr.php` each pair `require_login()` + `require_sesskey()` with a real domain check (`api::require_submission_details_access()`, `api::require_nomination_access()`). `audit.php` correctly gates on `moodle/site:config`.
- **Capabilities are complete.** All 8 declared in `db/access.php`, all 8 used, none referenced without declaration.
- **The system → course certificate context migration is coherent end to end** — upgrade step `2026081901`, `get_certificate_contextid()`, the privacy provider and the observer all agree. No code still writes certificates to the system context.
- **`version.php` is bumped consistently** with `db/install.xml`, and `db/install.php` / `db/upgrade.php` seed capabilities identically.
- **AMD build output is in sync** with `amd/src/`.
- **No secrets committed** anywhere in the branch.
- **`deploy_cluster.sh` hardening fixes a real footgun** — the old default `PLUGIN_NAME="batchanalytics"` in a spotaward repo would have rsync'd `--delete` over the wrong plugin directory. The new guards on `PLUGIN_NAME`, `PLUGIN_DIR` and `REMOTE_MOODLE_DIR` are a genuine improvement.
- All callers of the changed `get_*_submissions` / `get_*_dashboard_data` / `get_course_report` signatures were updated.
- The `{#s}…{/s}` regex fix in `process_language_strings()` is correct.

---

## Appendix — reproducing these checks

Run from the plugin root on the branch.

**Missing language strings (finding 2):**

```bash
php -r '
define("MOODLE_INTERNAL", true);
$string = []; include "lang/en/local_spotaward.php";
$defined = array_keys($string); $used = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(".")) as $f) {
    $p = $f->getPathname();
    if (strpos($p, "/.git/") !== false || strpos($p, "/docs/") !== false) continue;
    if (!preg_match("/\.(php|mustache|js)$/", $p)) continue;
    if (preg_match_all("/(?:get_string|new\s+lang_string|new\s+moodle_exception)\s*\(\s*\x27([a-zA-Z0-9_:]+)\x27\s*,\s*\x27local_spotaward\x27/", file_get_contents($p), $m)) {
        foreach ($m[1] as $k) $used[$k][] = $p;
    }
}
foreach (array_diff(array_keys($used), $defined) as $k) echo "MISSING: $k\n";'
```

**CFF-outline fonts (finding 3):**

```bash
php -r '
$src = file_get_contents("classes/local/constants.php");
preg_match("/signature_fonts_mapping.*?return \[(.*?)\];/s", $src, $m);
preg_match_all("/=> \x27([^\x27]+)\x27/", $m[1], $f);
foreach ($f[1] as $file) {
    $d = file_get_contents("fonts/$file");
    $n = unpack("nnum", substr($d, 4, 2))["num"]; $tags = [];
    for ($i = 0; $i < $n; $i++) $tags[] = substr($d, 12 + $i * 16, 4);
    if (!in_array("glyf", $tags)) echo "CFF (mPDF cannot embed): $file\n";
}'
```

**Status precedence regression (finding 1):**

```bash
git diff main...HEAD -- classes/local/api.php | grep -A2 -B2 'hasrejected\|hasclosed'
```

**Undefined variables (finding 4):**

```bash
grep -n 'ismanager\|isadmin' submission.php   # single hit at line 451, never assigned
```
