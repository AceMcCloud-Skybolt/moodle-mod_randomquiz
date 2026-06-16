<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Random quiz allocator';
$string['modulename'] = 'Random quiz allocator';
$string['modulenameplural'] = 'Random quiz allocators';
$string['randomquiz:addinstance'] = 'Add a random quiz allocator';
$string['randomquiz:view'] = 'View random quiz allocator';
$string['randomquiz:manage'] = 'Manage random quiz allocator';
$string['privacy:metadata:allocations'] = 'Stores the quiz variant allocated to each student.';
$string['privacy:metadata:allocations:randomquizid'] = 'The random quiz allocator instance.';
$string['privacy:metadata:allocations:userid'] = 'The user who received the allocation.';
$string['privacy:metadata:allocations:quizcmid'] = 'The quiz course module allocated to the user.';
$string['privacy:metadata:allocations:timeallocated'] = 'The time the allocation was made.';
$string['variantcmids'] = 'Quiz variants';
$string['variantcmids_help'] = 'Choose the existing Moodle quiz activities that students can be allocated to. For best results, make those quiz activities available but hidden from the course page.';
$string['allocationmode'] = 'Allocation mode';
$string['allocationmode_help'] = 'Random chooses any enabled variant. Balanced random chooses from the variants with the fewest existing allocations.';
$string['allocationmode:balanced'] = 'Balanced random';
$string['allocationmode:random'] = 'Random';
$string['novariants'] = 'No quiz variants have been selected yet.';
$string['nolaunchablevariants'] = 'No selected quiz variants are currently available to students.';
$string['allocationlocktimeout'] = 'Your quiz allocation is currently busy. Please wait a moment and try again.';
$string['notenoughvariants'] = 'Select at least two quiz variants.';
$string['studentready'] = 'Your quiz is ready.';
$string['studentreadyintro'] = 'When you start, your quiz variant will be selected. Your answers, navigation, autosave, timing and submission will be handled by Moodle\'s standard quiz activity.';
$string['startquiz'] = 'Start quiz';
$string['allocationlockedonstart'] = 'Once you start, your allocation will stay the same when you return to this activity.';
$string['allocationunavailable'] = 'Your assigned quiz is currently unavailable. Please contact your teacher before starting.';
$string['backtoactivity'] = 'Back to activity';
$string['teacherdashboard'] = 'Variant readiness dashboard';
$string['teacherdashboardintro'] = 'Students see this one activity. When they start, Moodle stores their allocation and redirects them to the assigned quiz variant.';
$string['badgelocksonlaunch'] = 'Locks when started';
$string['badgeusesmoodlequizzes'] = 'Uses normal Moodle quizzes';
$string['badgehighestgradecategory'] = 'Gradebook: highest grade category';
$string['variant'] = 'Variant';
$string['variantlabel'] = 'Variant {$a}';
$string['quizshell'] = 'Quiz shell';
$string['readinesschecks'] = 'Readiness checks';
$string['sharedsettings'] = 'Shared settings';
$string['navigation'] = 'Navigation';
$string['navigationfree'] = 'Free';
$string['navigationsequential'] = 'Sequential';
$string['allocations'] = 'Allocations';
$string['student'] = 'Student';
$string['allocatedquiz'] = 'Allocated quiz';
$string['attemptstatus'] = 'Attempt status';
$string['noallocations'] = 'No students have been allocated yet.';
$string['launchassignedquiz'] = 'Launch assigned quiz';
$string['availablehidden'] = 'Available, hidden from course page';
$string['visibleoncoursepage'] = 'Visible on course page';
$string['hiddenfromstudents'] = 'Hidden from students';
$string['hasquestions'] = 'Has questions';
$string['noquestions'] = 'No questions yet';
$string['matchsettings'] = 'Match settings from Variant A';
$string['settingsmatched'] = 'Quiz settings were matched from the first variant.';
$string['settingsmatchedcount'] = 'Quiz settings were matched from {$a->source} to {$a->count} other variants.';
$string['settingsmatchnone'] = 'There are not enough variants to match settings.';
$string['settingdiff:timeopen'] = 'Open date differs';
$string['settingdiff:timeclose'] = 'Close date differs';
$string['settingdiff:timelimit'] = 'Time limit differs';
$string['settingdiff:attempts'] = 'Attempts allowed differs';
$string['settingdiff:grademethod'] = 'Grading method differs';
$string['settingdiff:grade'] = 'Maximum grade differs';
$string['settingdiff:shuffleanswers'] = 'Shuffle answers differs';
$string['settingdiff:browsersecurity'] = 'Browser security differs';
$string['gradebooksetup'] = 'Create/check grade category';
$string['gradebookready'] = 'Gradebook ready';
$string['gradebookneedssetup'] = 'Gradebook needs setup';
$string['gradebookcategory'] = 'Grade category';
$string['gradebooknocategory'] = 'Variant quiz grade items are not all in one category.';
$string['gradebookwrongaggregation'] = 'Category aggregation is not Highest grade.';
$string['gradebookmissingitems'] = 'Some variant quiz grade items could not be found.';
$string['gradebooksetupdone'] = 'Grade category "{$a->category}" now contains {$a->count} variant quiz grade items and uses Highest grade aggregation.';
$string['gradebookmvpnote'] = 'MVP note: the gradebook helper creates a highest-grade category for the variant quiz grade items. Review the gradebook if your course already uses a complex grading structure.';
$string['attemptnotstarted'] = 'No attempt started';
$string['attemptstarted'] = 'Attempt started';
$string['attemptlocked'] = 'Allocation locked';
$string['resetallocation'] = 'Reset allocation';
$string['allocationreset'] = 'Allocation reset for {$a}. The student will receive a new allocation when they next open this activity.';
$string['allocationresetblocked'] = 'This allocation cannot be reset because the student has already started the assigned quiz.';
$string['allocationnotfound'] = 'Allocation not found.';
$string['manualallocation'] = 'Manual allocation';
$string['manualallocation_help'] = 'Assign or change a student allocation before they start an attempt.';
$string['selectstudent'] = 'Select student';
$string['selectvariant'] = 'Select quiz variant';
$string['setallocation'] = 'Set allocation';
$string['allocationupdated'] = 'Allocation updated for {$a}.';
$string['manualallocationblocked'] = 'This allocation cannot be changed because the student has already started the assigned quiz.';
$string['invalidallocationuser'] = 'The selected user cannot be allocated in this course.';
$string['variantquizmanagepermissionrequired'] = 'You need permission to manage each selected quiz variant before matching settings.';
$string['gradebookmanagepermissionrequired'] = 'You need permission to manage the course gradebook before creating or repairing the grade category.';
$string['eventallocationcreated'] = 'Quiz allocation created';
$string['eventallocationreset'] = 'Quiz allocation reset';
$string['eventassignedquizlaunched'] = 'Assigned quiz launched';
$string['eventgradebooksetupcompleted'] = 'Gradebook setup completed';
$string['eventmanualallocationupdated'] = 'Manual allocation updated';
$string['eventsettingssynced'] = 'Variant quiz settings synced';
$string['restoreinsufficientvariants'] = 'The restored random quiz allocator "{$a->name}" has {$a->count} linked quiz variant(s). Add at least two quiz variants before using it with students.';
