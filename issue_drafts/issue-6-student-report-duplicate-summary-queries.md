## Summary
The student report path builds the full activity report and then immediately runs additional attendance/assignment/project summary queries for the same course and student, duplicating database work on every page load.

## Affected code
- `classes/local/api.php:4399`
- `classes/local/api.php:4409`
- `classes/local/api.php:5181`
- `classes/local/api.php:5242`
- `classes/local/api.php:5280`

## Current behavior
`get_student_report()` does both:
- `build_course_activity_report($courseid, [$student], $activitytype)`, which already loads activity/report data
- separate summary helpers:
  - `get_attendance_percentage()`
  - `get_assignment_completion()`
  - `get_project_completion()`

Each helper runs more SQL for the same course/student combination.

## Expected behavior
The report should reuse already-loaded activity data where possible or batch summary metrics in one pass instead of issuing extra queries after the main report has already been assembled.

## Why this matters
This page is interactive. Repeating summary queries per report view makes the report heavier than necessary and increases database load as usage grows.
