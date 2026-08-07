$ErrorActionPreference = 'Stop'
$env:GITHUB_TOKEN=""

Write-Host "Committing changes..."
git add .
git commit -m "Enhance PDF templates, fix icons, adjust layout, add Best Problem Solver suggestions"
git push origin main

Write-Host "Creating GitHub Issues..."
gh issue create -R muruganantham-v/moodle-local_spotaward --title "MAAC Executive Share Admin Auth Inconsistency" --body-file .\issue_drafts\issue-1-maac-share-admin-auth.md --label "bug"
gh issue create -R muruganantham-v/moodle-local_spotaward --title "Admin Share Recipient Email Leak" --body-file .\issue_drafts\issue-2-admin-share-recipient-leak.md --label "bug"
gh issue create -R muruganantham-v/moodle-local_spotaward --title "PDF Generation Memory Pressure" --body-file .\issue_drafts\issue-3-pdf-memory-pressure.md --label "efficiency"
gh issue create -R muruganantham-v/moodle-local_spotaward --title "Dashboard Report Query Cost" --body-file .\issue_drafts\issue-4-dashboard-report-query-cost.md --label "efficiency"
gh issue create -R muruganantham-v/moodle-local_spotaward --title "PR Document Upload Validation" --body-file .\issue_drafts\issue-5-pr-upload-validation.md --label "enhancement"
gh issue create -R muruganantham-v/moodle-local_spotaward --title "Student Report Duplicate Summary Queries" --body-file .\issue_drafts\issue-6-student-report-duplicate-summary-queries.md --label "efficiency"

Write-Host "Done!"
