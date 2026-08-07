## Summary
Dashboard/report queries rely on repeated correlated subqueries and full-course scans, which will become expensive as the site grows.

## Affected code
- `classes/local/api.php:5371`
- `classes/local/api.php:5508`
- `classes/local/api.php:5556`
- `classes/local/api.php:4587`

## Current behavior
Examples:
- Manager dashboard query runs multiple per-row subqueries for item counts and certificate existence.
- Global dashboard data repeats the same pattern.
- Admin dashboard loads shared nominations without pre-aggregated counters.
- Report course discovery scans the full `course` table and then evaluates every record in PHP.

## Expected behavior
Replace repeated subqueries/full scans with lighter patterns such as:
- pre-aggregated joins
- grouped derived tables
- cached report-course lists
- targeted filters/index-friendly queries

## Why this matters
These queries run on interactive pages. As nominations, files, and courses grow, the plugin can add unnecessary DB load and slower dashboards.
