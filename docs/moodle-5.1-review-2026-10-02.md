# Random Quiz: Moodle 5.1 compatibility review

## Scope and environment

Reviewed on 2 October 2026 against Moodle 5.1.4+ (Build: 20260604), PHP 8.2.4, MariaDB 10.11.11 and PHPUnit 11.5.55 in an isolated Windows test installation. No university-site data was accessed or changed.

This is compatibility evidence, not production certification. The institution's target Moodle patch release, theme, role configuration, integrations and upgrade path still require acceptance testing. See Moodle's [5.1 developer update](https://moodledev.io/docs/5.1/devupdate) for the upstream API changes.

## Automated evidence

- PHP syntax, Moodle coding standard (zero warnings), PHPDoc, plugin structure and upgrade savepoint checks pass.
- Literal language-string references were checked against the installed Moodle 5.1 string manager; no missing references remain. Dynamic identifiers need workflow testing too.
- PHPUnit results: pending final run.
- Template lint on Windows encountered an upstream mixed-path-separator limitation; Linux GitHub Actions runs the installed-plugin template checks.

## Changes

- Correct the invalid-course-module validation message to use Moodle's error language component.
- Add settings-form rendering and invalid-quiz-selection regression coverage.
- Extend GitHub Actions coverage from PHP 8.2 to PHP 8.2 and 8.3.

Release: **0.1.6**, plugin version **2026100200**. No database schema change.

## Acceptance before rollout

Test allocation/launch as students, hidden and restricted quiz variants, attempted-allocation persistence, manual allocation permissions, grade/completion propagation, reset and backup/restore. Check that invalid or cross-course variant selections produce a clear validation error. Do not reset students' allocations after attempts without evaluating the documented safeguards.

