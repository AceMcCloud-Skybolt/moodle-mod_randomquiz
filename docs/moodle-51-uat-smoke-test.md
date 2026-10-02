# Moodle 5.1 UAT Smoke Test

Use this checklist on the university upgrade environment before enabling Random Quiz Allocator for teaching.

## Test Record

- Date / tester:
- Moodle version / build:
- PHP version / database:
- Plugin release / version / Git commit:
- Course / teacher account / student accounts:
- Result: Pass / Fail / Blocked; record screenshots and reproduction steps for failures.

## Preparation

Install the reviewed Git revision into `public/mod/randomquiz`, run the Moodle upgrade and purge caches. Use a disposable course, one teacher and at least four enrolled student accounts. Create two quizzes with questions, equal maximum grades and compatible attempt settings. Make both available but hidden from the course page (stealth), rather than fully hidden. Include a multi-page quiz for save/resume checks. Use real student accounts, not only teacher role switching.

## Smoke Tests

| # | Workflow | Expected result | Result |
| --- | --- | --- | --- |
| 1 | Install, or upgrade an existing plugin installation; reopen an existing allocator. | Upgrade completes without plugin errors; existing settings and allocations remain. | |
| 2 | Teacher creates an allocator, selects two quizzes, saves and edits it again. | Activity and choices persist; no missing language strings or broken controls. | |
| 3 | Select fewer than two variants; then deliberately change one quiz time limit/navigation setting. | Invalid selection is rejected; dashboard reports relevant mismatches. | |
| 4 | Use Match settings from Variant A; inspect both underlying quizzes. | Supported settings align with A; questions, answers and attempts remain intact. | |
| 5 | Use Create/check grade category twice. | One appropriate Highest grade category contains the variant grade items; no duplicate category. | |
| 6 | Students launch in balanced mode, then revisit, refresh and double-click launch. | Each gets one persistent allocation and enters the assigned quiz; no duplicate allocation or exception. | |
| 7 | In a separate allocator use random mode with fresh students. | Every allocation belongs to the selected eligible pool; both variants need not appear in a small sample. | |
| 8 | Answer questions, move Next, wait through configured autosave, close and resume the attempt. | Saved answers return in the same quiz. Confirm your site's actual autosave interval; do not assume every keystroke saves immediately. | |
| 9 | Exercise free navigation and sequential navigation in separate quiz setups. | Native Quiz allows jumping in free mode and enforces order in sequential mode. | |
| 10 | Submit an attempt; inspect student review and teacher gradebook. | Submission/review follow quiz settings; category grade reflects the completed variant correctly. | |
| 11 | Teacher manually assigns and resets an unattempted allocation; repeat after a real attempt starts. | Changes work before an attempt and are blocked afterward; attempt data remains. | |
| 12 | Apply group/date/access restrictions, fully hide a variant, and try a non-enrolled account. | New allocations respect eligibility; an attempted allocation to an unavailable quiz stays preserved with a clear blocked-launch message; outsiders cannot manage or launch. | |
| 13 | Inspect course logs after allocation, launch, manual change, reset and settings sync. | Relevant actions identify the actor and affected allocation/student where applicable. | |
| 14 | Back up and restore the whole course including variants; open the restored allocator. Also restore an allocator-only backup. | Whole-course variant links map to restored quizzes; missing variants produce a warning/readiness failure rather than broken links. Check user-data preservation only when included in backup. | |
| 15 | Repeat student launch and submission in the university theme, supported browsers and a narrow/mobile viewport. | Controls remain readable and usable; no theme/SSO/session errors. | |

## Sign-Off

Record defects with account role, exact steps, expected/actual result and logs. Resolve blocking failures and retest before approval. Include a brief simultaneous-launch check with several students for the anticipated classroom workflow; a smoke test does not establish load capacity. Have the Moodle administrator and academic tester record their acceptance and the exact approved Git commit.

## Existing Automated Evidence

The repository's GitHub Actions workflow targets `MOODLE_501_STABLE`, PHP 8.2 and MariaDB 10.11, including PHPUnit and Moodle plugin checks. Local staging identifies itself as Moodle 5.1.4+ (Build 20260604). Check the Actions result for the exact installation commit; these checks supplement UAT on the university's own environment.
