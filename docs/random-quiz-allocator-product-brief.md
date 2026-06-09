# Random Quiz Allocator Product Brief

## Summary

Random Quiz Allocator is a proposed Moodle activity that lets teaching staff present students with one visible quiz entry point while Moodle assigns each student to one of several pre-built Moodle quiz variants.

The aim is not to replace Moodle Quiz. The aim is to add a controlled allocation layer in front of normal Moodle quiz activities, so staff can randomise whole quiz variants while still relying on Moodle Quiz for attempts, autosave, navigation, timing, submission, grading and review.

## Problem Statement

Staff sometimes need students to receive different fixed versions of a quiz, not just randomly selected questions from a shared question bank. This is especially relevant for in-class tests, case-based assessments, practical scenarios and situations where staff are worried about students sitting together, sharing answers or discussing the assessment while it is underway.

Moodle can randomise questions from question banks very well, but it does not provide a simple teacher-facing workflow for randomly assigning students to different complete quiz activities.

## Current Moodle Gap

The standard Moodle Quiz workflow supports random questions from categories in a question bank. This works well when all questions are interchangeable within a category.

It is less suitable when each student must receive a complete fixed variant, such as:

- a scenario with several linked questions
- a case study with dependent parts
- a practical troubleshooting pathway
- a different version of the same quiz with carefully matched questions
- an alternate assessment version with its own fixed structure

The current workaround is usually to:

1. Create multiple Moodle quizzes.
2. Put students into groups.
3. Restrict each quiz to a different group.
4. Hide or manage several activity links.
5. Manually check that settings, dates and gradebook behaviour match.

That workflow is possible, but it is slow, fragile and easy for staff to misconfigure.

## Why Existing Workarounds Are Not Ideal

Manual groups can work for planned cohorts, but they are awkward for live classes where staff do not know where students will sit or who will attend. They also create setup overhead for teaching teams and can make last-minute changes stressful.

Multiple visible quiz links can confuse students. Multiple hidden quiz links can confuse staff. Restrict access rules are powerful, but they are not a natural workflow for the simple teaching intention: "give each student one of these quiz versions."

Gradebook setup is another pain point. Staff may need a category that takes the highest grade from the variant quizzes, but this is not obvious to all users and can be risky in courses with more complex grading structures.

## Target Users

- Academics who need to run in-class or online quizzes with different complete variants.
- Tutors and sessional staff who support quiz setup and delivery.
- Learning designers and educational technologists who help staff translate assessment designs into Moodle.
- Students who need a simple, low-friction quiz entry point.
- Moodle administrators who need a supportable solution that stays inside Moodle's quiz, gradebook, backup, privacy and reporting ecosystem.

## Core Use Cases

- In-class quiz where neighbouring students should receive different versions.
- Case-based quiz where each case has a fixed set of linked questions.
- Practical or lab assessment where students receive different diagnostic scenarios.
- Workshop or tutorial quiz where staff want balanced random distribution across several quiz versions.
- Alternate quiz versions for deferred, makeup or supplementary assessment.
- Large enrolment courses where manually grouping students is too slow.
- Courses where staff prefer whole-quiz variants over question-bank randomisation.

## Proposed Workflow

1. Teacher creates the Moodle quiz activities that will act as variants.
2. Teacher hides those variant quizzes from the course page but keeps them available.
3. Teacher creates one Random Quiz Allocator activity.
4. Teacher selects the quiz variants.
5. Teacher checks the readiness dashboard for visibility, question and settings issues.
6. Teacher optionally uses helper actions to align settings and gradebook setup.
7. Student clicks the single Random Quiz Allocator activity.
8. Moodle stores that student's assigned variant.
9. Student clicks Start quiz and is sent into the assigned standard Moodle quiz.

## MVP Requirements

- Activity module that can be added to a Moodle course.
- Teacher can select two or more existing Moodle quiz activities as variants.
- Teacher can choose an allocation mode, initially balanced random and pure random.
- Student receives one stored allocation per Random Quiz Allocator activity.
- Student allocation remains stable after first allocation.
- Student sees one clean launch screen before entering the assigned quiz.
- Assigned quiz opens as a normal Moodle quiz activity.
- Teacher dashboard shows selected variants and basic readiness checks.
- Dashboard warns when variant quiz settings differ on key fields.
- Teacher can copy safe shared settings from Variant A to other variants.
- Teacher can create/check a gradebook category using Highest grade aggregation.
- Teacher can view allocation report.
- Teacher can reset an allocation before the student starts a real quiz attempt.
- Teacher can manually assign or change an allocation before the student starts a real quiz attempt.
- Backup and restore should preserve allocator data where referenced quiz activities are included.
- Privacy provider should export and delete stored allocation data.

## Student Experience Requirements

- Student should only need to click one visible activity.
- Student should not need to choose a variant.
- Student should not see the teacher dashboard.
- Student should receive clear wording that their quiz is ready.
- Student should be sent into Moodle's standard quiz workflow.
- Moodle Quiz should continue to handle autosave, navigation mode, time limits, submission, grading and review.

## Teacher Experience Requirements

- Setup should feel like a normal Moodle activity setup task.
- Staff should not need to manually create student groups for basic randomisation.
- Staff should receive warnings before students enter badly configured variants.
- Staff should be able to see which students have been allocated to which variant.
- Staff should be protected from accidentally changing allocations after attempts have started.
- Staff should be guided toward a sensible gradebook setup without the plugin silently changing complex course gradebooks in surprising ways.

## Future And Non-MVP Ideas

- More allocation strategies, such as seeded random, group-aware allocation or imported allocation lists.
- Capacity limits per variant.
- Teacher preview mode for each student outcome.
- More detailed reporting and CSV export.
- Better large-course manual allocation UI with search/autocomplete.
- Optional automatic hiding of selected variant quizzes from the course page.
- Stronger preflight checks before release to students.
- Integration with Moodle question statistics or quiz reports.
- Course-copy and restore assistant for cases where referenced quiz variants are missing.
- Analytics dashboard showing variant balance and attempt progress.

## Questions For Developers

- Should allocation occur when the student first views the allocator, or only when they click Start quiz?
- Should teachers be allowed to reallocate students after a quiz attempt exists under any circumstances?
- Should the plugin ever hide variant quizzes automatically, or only warn staff?
- Should mismatched settings block release to students, or remain warnings?
- Should the gradebook helper create a category automatically, or require explicit teacher confirmation every time?
- Should the Random Quiz Allocator have its own grade item, or should it rely entirely on the variant quiz grade items and gradebook category aggregation?
- What is the best backup/restore behaviour when referenced quiz variants are not included in the same backup?
- Should allocations be included in course copy by default, or treated as user data only?
- How should this behave in group mode or separate groups?
- How should this scale in courses with thousands of students?
- What events should be logged for allocation, reset, manual override and launch?
- Are there Moodle Quiz APIs we should use instead of directly reading or updating quiz records?
- Should this be a standalone activity module, or is there a better architecture using quiz APIs, availability conditions or another Moodle extension point?

## Current Prototype

The current prototype demonstrates the main concept as a standalone Moodle activity module:

- one visible student entry point
- teacher-selected quiz variants
- stored student allocations
- balanced random allocation
- readiness checks
- settings matching helper
- gradebook category helper
- allocation report
- manual allocation and reset controls
- Moodle 5.1 compatibility checks

The prototype is useful for discussion and validation, but it should be treated as an exploratory build rather than a finished production design.

## Decision Needed

The next decision is whether the concept is valuable enough to move from prototype into a properly scoped development task.

If yes, the next step should be a requirements review with developers, learning designers and representative teaching staff. The main purpose of that review should be to challenge assumptions, confirm the correct Moodle architecture, and decide what belongs in the first production-ready version.
