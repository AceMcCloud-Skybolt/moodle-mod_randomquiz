# Security Review Response: 2026-06-12

This document responds to the external security review of `mod_randomquiz` dated 2026-06-10.

## Addressed Findings

### State-changing allocation on ordinary view

Accepted and fixed.

Previously, a student allocation could be created by opening the activity page. Allocation now happens only when the student submits the **Start quiz** action, which is POST-based and sesskey-protected.

### Settings sync missing linked quiz capability checks

Accepted and fixed.

The **Match settings from Variant A** action now requires `mod/quiz:manage` on every selected quiz variant before any linked quiz settings are changed. The dashboard only shows the action to users who have the required quiz-management capability across all selected variants.

### Gradebook setup missing gradebook capability check

Accepted and fixed.

The **Create/check grade category** action now requires `moodle/grade:manage` in the course context before gradebook categories or grade items are changed. The dashboard only shows the action to users who can manage the course gradebook.

### Manual allocation accepts arbitrary user IDs

Accepted and fixed.

Manual allocation now revalidates the submitted user server-side. The selected user must:

- exist and not be deleted
- not be suspended
- be actively enrolled in the course
- have `mod/quiz:attempt` for the selected quiz variant

The selected quiz variant must also belong to the allocator course and be visible/available to students.

## Additional Hardening

### Fully hidden variants are no longer allocated

The allocator now chooses from enabled variants that are visible to students. Variant quizzes may still be hidden from the course page, but they must not be fully hidden/unavailable. This prevents students being allocated into a quiz that Moodle Quiz will then refuse to launch.

### Stale unattempted allocations are repaired on start

If a stored allocation points to a variant that is no longer launchable and the student has not started a real attempt, the stale allocation is removed and replaced when the student explicitly starts the allocator.

## Verification

After patching, the following checks were run against the local Moodle 5.1 instance:

- PHP syntax checks across all plugin PHP files.
- Moodle CLI upgrade for plugin version `2026061200`.
- Browser check of the teacher dashboard.
- Browser check that a clean student can view the launch page without creating an allocation.
- Browser check that submitting **Start quiz** creates the allocation and redirects to Moodle Quiz.
- CLI check that invalid manual allocation users are rejected.
- CLI check that valid manual allocation still works.
- Moodle CLI activity backup smoke test.

Temporary local test helpers and test allocation rows were removed after verification.

## Remaining Non-Security Follow-Up

The review also raised useful lifecycle and integration questions that should be considered before production release:

- Restore can leave fewer than two mapped variants if referenced quizzes are not included.
- Existing attempted allocations to removed/unavailable quiz modules need a deliberate product policy.
- Larger courses need a better manual allocation selector.
- Events/audit logging should be added for allocation, reset, manual override and launch.
