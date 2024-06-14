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
 * Links and settings
 *
 * Class containing a set of helpers, based on admin\tool\uploadcourse by 2013 Frédéric Massart.
 *
 * @package    tool_uploadactivitycompletions
 * @copyright  2020 Tim St.Clair (https://github.com/frumbert/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . '/mod/page/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Class containing a set of helpers.
 *
 * @package   tool_uploadactivitycompletions
 * @copyright 2020 Tim St.Clair (https://github.com/frumbert/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadactivitycompletions_helper {
    /**
     * Validate we have the minimum info to create/update course result
     *
     * @param object $record The record we imported
     * @return bool true if validated
     */
    public static function validate_import_record($record) {
        if (empty($record->coursevalue)) {
            return false;
        }
        if (empty($record->uservalue)) {
            return false;
        }
        if (empty($record->sectionname) && strlen($record->sectionname) === 0) {
            return false;
        }
        if (empty($record->activityname) && strlen($record->activityname) === 0) {
            return false;
        }
        return true;
    }

    /**
     * Retrieve a course by its required column name.
     *
     * @param string $field name (e.g. idnumber, shortname)
     * @return object course or null
     */
    public static function get_course_by_field($field, $value) {
        global $DB;

        $courses = $DB->get_records('course', [$field => $value]);

        if (count($courses) == 1) {
            $course = array_pop($courses);
            return $course;
        } else {
            return null;
        }
    }

    /**
     * Retrieve a user by its required column name.
     *
     * @param string $field name (e.g. idnumber, username)
     * @return object course or null
     */
    public static function get_user_by_field($field, $value) {
        global $DB;

        $users = $DB->get_records('user', [$field => $value]);

        if (count($users) == 1) {
            $users = array_pop($users);
            return $users;
        } else {
            return null;
        }
    }

    // given a course, section name and activity name, return the modinfo instance (cm)
    // if section_name is "0" and section->name is null, it represents the first (un-namable) section
    public static function find_activity_in_section($course,$section_name,$activity_name) {
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cms();
        foreach ($cminfo as $inst) {
            $section = $inst->get_section_info();
            if ((($section_name === "0" && is_null($section->name)) || strcasecmp($section_name, $section->name) == 0) && strcasecmp($activity_name, $inst->name) == 0) {
                return $inst;
            }
        }
        return null;
    }

    /**
     * Update page activity viewed
     *
     * This will show a developer debug warning when run in Moodle UI because
     * of the function set_module_viewed in completionlib.php details copied below:
     *
     * Note that this function must be called before you print the page header because
     * it is possible that the navigation block may depend on it. If you call it after
     * printing the header, it shows a developer debug warning.
     *
     * @param object $record Validated Imported Record
     * @param integer $studentrole role value of a student
     * @return object $response contains details of processing
     */
    public static function mark_activity_as_completed($record, $studentrole) {
    global $DB, $USER;

    $response = new \stdClass();
    $response->added = 0;
    $response->skipped = 0;
    $response->updated = 0;
    $response->error = 0;
    $response->message = null;

    // Log start of function
    //error_log('Start of mark_activity_as_completed function');

    // Validate the student role object
    if (!is_object($studentrole) || !isset($studentrole->id)) {
        //error_log('Invalid student role object: ' . print_r($studentrole, true));
        $response->message = 'Invalid student role object';
        $response->error = 1;
        return $response;
    }

    // Get the course record.
    if ($course = self::get_course_by_field($record->coursefield, $record->coursevalue)) {
        //error_log('Course found: ' . $course->fullname);
        $response->course = $course;

        $completion = new completion_info($course);
        if ($completion->is_enabled()) {
            //error_log('Completion is enabled for course');

            // Get the user record.
            if ($user = self::get_user_by_field($record->userfield, $record->uservalue)) {
                //error_log('User found: ' . $user->username);
                $response->user = $user;
                if ($cm = self::find_activity_in_section($course, $record->sectionname, $record->activityname)) {
                    //error_log('Activity found: ' . $record->activityname);

                    // Ensure the user is enrolled in this course.
                    enrol_try_internal_enrol($course->id, $user->id, $studentrole->id);
                    //error_log('User enrolled in course');

                    // Test the current completion state to avoid re-completion.
                    $currentstate = $completion->get_data($cm, false, $user->id, null);
                    //error_log('Current completion state: ' . $currentstate->completionstate);

                    if ($currentstate->completionstate == COMPLETION_COMPLETE) {
                        //error_log('Activity already completed');
                        // Activity already completed, update the completion date if needed.
                        $completion_record = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $user->id, 'completionstate' => 1));
                        if ($completion_record) {
                            //error_log('Completion record found');
                            if ($completion_record->timemodified != $record->completiondate) {
                                //error_log('Updating completion date from ' . $completion_record->timemodified . ' to ' . $record->completiondate);
                                $completion_record->timemodified = $record->completiondate;
                                $DB->update_record('course_modules_completion', $completion_record);
                                //error_log('Completion date updated');
                                $response->message = 'Activity "' . $record->activityname . '" in topic "' . $record->sectionname . '" was already completed but the completion date was updated.';
                                $response->updated = 1;
                            } else {
                                //error_log('Completion date is the same, no update needed');
                                $response->message = 'Activity "' . $record->activityname . '" in topic "' . $record->sectionname . '" was already completed and the completion date is the same.';
                                $response->skipped = 1;
                            }
                        } else {
                            //error_log('Completion record not found');
                        }
                    } else {
                        //error_log('Activity not completed, attempting to complete it');

                        // Ensure the user can override completion
                        if (!$completion->user_can_override_completion($USER)) {
                            $response->message = 'Configured user unable to override completion in course ' . $course->fullname;
                            $response->skipped = 1;
                            //error_log('User cannot override completion');
                        } else {
                            // Log before updating state
                            //error_log('Updating state to COMPLETION_COMPLETE');
                            try {
                                $update_result = $completion->update_state($cm, COMPLETION_COMPLETE, $user->id, true);
                                //error_log('update_state returned: ' . ($update_result ? 'true' : 'false'));

                                // If update_state is successful, proceed with updating the date
                                if ($update_result || $completion->get_data($cm, false, $user->id, null)->completionstate == COMPLETION_COMPLETE) {
                                    //error_log('Completion state updated');

                                    // Clear relevant caches immediately
                                    cache_helper::purge_by_definition('core', 'completion');
                                    //error_log('Caches purged');

                                    // Loop to check for the record before updating the completion date.
                                    for ($i = 0; $i < 10; $i++) { // Try up to 10 times.
                                        $completion_record = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $user->id, 'completionstate' => 1));
                                        if ($completion_record) {
                                            //error_log('Completion record found after state update');
                                            break;
                                        }
                                        usleep(50000); // Wait 50 milliseconds before retrying.
                                    }

                                    if ($completion_record) {
                                        //error_log('Setting completion date to ' . $record->completiondate);
                                        $completion_record->timemodified = $record->completiondate;
                                        $DB->update_record('course_modules_completion', $completion_record);
                                        //error_log('Completion date set after state update');
                                        $response->message = 'Activity "' . $record->activityname . '" in topic "' . $record->sectionname . '" was completed on behalf of user.';
                                        $response->added = 1;

                                        // Ensure course completion and criteria dates are also updated.
                                        $course_completion_record = $DB->get_record('course_completions', array('userid' => $user->id, 'course' => $course->id));
                                        if ($course_completion_record) {
                                            //error_log('Setting course completion date to ' . $record->completiondate);
                                            $course_completion_record->timecompleted = $record->completiondate;
                                            $course_completion_record->timestarted = $record->completiondate;
                                            $DB->update_record('course_completions', $course_completion_record);
                                            //error_log('Course completion date updated');
                                        }

                                        $course_completion_criteria = $DB->get_record('course_completion_crit_compl', array('userid' => $user->id, 'course' => $course->id));
                                        if ($course_completion_criteria) {
                                            //error_log('Setting course completion criteria date to ' . $record->completiondate);
                                            $course_completion_criteria->timecompleted = $record->completiondate;
                                            $DB->update_record('course_completion_crit_compl', $course_completion_criteria);
                                            //error_log('Course completion criteria date updated');
                                        }
                                    } else {
                                        $response->message = 'Failed to retrieve the completion record for updating.';
                                        $response->error = 1;
                                        //error_log('Failed to retrieve completion record for updating');
                                    }
                                } else {
                                    //error_log('Failed to update completion state');
                                }
                            } catch (Exception $e) {
                                //error_log('Exception in update_state: ' . $e->getMessage());
                                $response->message = 'Exception occurred while updating completion state: ' . $e->getMessage();
                                $response->error = 1;
                            }
                        }
                    }
                } else {
                    $response->message = 'Unable to find activity "' . $record->activityname . '" in topic "' . $record->sectionname . '" in course "' . $course->fullname . '"';
                    $response->skipped = 1;
                    //error_log('Activity not found');
                }
            } else {
                $response->message = 'Unable to find user matching "' . $record->uservalue . '"';
                $response->skipped = 1;
                //error_log('User not found');
            }
        } else {
            $response->message = 'Course "' . $course->fullname . '" does not have completions enabled';
            $response->skipped = 1;
            //error_log('Completion not enabled for course');
        }
    } else {
        $response->message = 'Unable to find course matching "' . $record->coursevalue . '"';
        $response->skipped = 1;
        //error_log('Course not found');
    }
    //error_log('End of mark_activity_as_completed function');
    return $response;
}

}