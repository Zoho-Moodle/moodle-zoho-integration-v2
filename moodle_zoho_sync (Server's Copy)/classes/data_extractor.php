<?php
/**
 * Data Extractor for Moodle-Zoho Integration
 * Extracts data from Moodle database tables
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync;

defined('MOODLE_INTERNAL') || die();

class data_extractor {

    /**
     * Extract user data from mdl_user table
     * 
     * @param int $userid
     * @return array|null User data or null if not found
     */
    public function extract_user_data($userid) {
        global $DB;

        try {
            $user = $DB->get_record('user', ['id' => $userid]);

            if (!$user) {
                return null;
            }

            // Skip deleted or suspended users
            if ($user->deleted == 1 || $user->suspended == 1) {
                return null;
            }

            // Determine user role (student or teacher)
            $role = $this->get_user_primary_role($userid);

            return [
                'userid' => (int)$user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'phone1' => $user->phone1 ?? '',
                'phone2' => $user->phone2 ?? '',
                'city' => $user->city ?? '',
                'country' => $user->country ?? '',
                'role' => $role,
                'timecreated' => (int)$user->timecreated,
                'timemodified' => (int)$user->timemodified,
            ];

        } catch (\Exception $e) {
            error_log('[Data Extractor ERROR] extract_user_data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract enrollment data from mdl_user_enrolments table
     * 
     * @param int $enrolmentid
     * @return array|null Enrollment data or null if not found
     */
    public function extract_enrollment_data($enrolmentid) {
        global $DB;

        try {
            // Get enrollment record with enrolment method details
            $sql = "SELECT ue.id, ue.userid, ue.status, ue.timestart, ue.timeend,
                           ue.timecreated, ue.timemodified,
                           e.courseid, e.enrol as enrol_method,
                           c.fullname as course_name, c.shortname as course_shortname
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {course} c ON c.id = e.courseid
                    WHERE ue.id = :enrolmentid";

            $enrollment = $DB->get_record_sql($sql, ['enrolmentid' => $enrolmentid]);

            if (!$enrollment) {
                return null;
            }

            return [
                'enrollment_id' => (int)$enrollment->id,
                'userid' => (int)$enrollment->userid,
                'courseid' => (int)$enrollment->courseid,
                'course_name' => $enrollment->course_name,
                'course_shortname' => $enrollment->course_shortname,
                'status' => (int)$enrollment->status, // 0 = active, 1 = suspended
                'enrol_method' => $enrollment->enrol_method,
                'timestart' => (int)$enrollment->timestart,
                'timeend' => (int)$enrollment->timeend,
                'timecreated' => (int)$enrollment->timecreated,
                'timemodified' => (int)$enrollment->timemodified,
            ];

        } catch (\Exception $e) {
            error_log('[Data Extractor ERROR] extract_enrollment_data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract grade data from mdl_grade_grades table
     * 
     * @param int $gradeid
     * @return array|null Grade data or null if not found
     */
    /**
     * Extract grade data for webhook payload - PRODUCTION HARDENED
     * 
     * @param int $gradeid Grade ID
     * @return array|null Grade data array or null if invalid
     */
    public function extract_grade_data($gradeid, $grader_userid = null) {
        global $DB;

        try {
            // Defensive check: ensure gradeid is valid
            if (empty($gradeid) || !is_numeric($gradeid)) {
                error_log('[Data Extractor ERROR] extract_grade_data: Invalid grade ID provided');
                return null;
            }

            // Get grade with item details - proper JOIN to get all related data
                 $sql = "SELECT gg.id, gg.userid, gg.itemid, gg.finalgrade, 
                          gg.timecreated, gg.timemodified,
                          gi.itemname, gi.itemtype, gi.itemmodule, gi.iteminstance,
                          gi.grademax, gi.grademin, gi.courseid,
                          c.fullname as course_name
                      FROM {grade_grades} gg
                      JOIN {grade_items} gi ON gi.id = gg.itemid
                      LEFT JOIN {course} c ON c.id = gi.courseid
                      WHERE gg.id = :gradeid";

            $grade = $DB->get_record_sql($sql, ['gradeid' => $gradeid]);

            // Defensive check: grade must exist and have a valid final grade
            if (!$grade) {
                error_log('[Data Extractor ERROR] extract_grade_data: Grade not found for ID ' . $gradeid);
                return null;
            }
            
            if (is_null($grade->finalgrade)) {
                error_log('[Data Extractor DEBUG] extract_grade_data: Grade has no finalgrade yet (ID ' . $gradeid . ')');
                return null;
            }

            // CRITICAL FIX: Fetch user data properly (was missing in original)
            $user = $DB->get_record('user', ['id' => $grade->userid], 'id,username,email,firstname,lastname');
            if (!$user) {
                error_log('[Data Extractor ERROR] extract_grade_data: User not found for user ID ' . $grade->userid);
                return null;
            }

            // CRITICAL FIX: Fetch course data properly (was missing in original)
            // Even though we have LEFT JOIN above, explicitly check courseid
            $course = null;
            if (!empty($grade->courseid)) {
                $course = $DB->get_record('course', ['id' => $grade->courseid], 'id,fullname,shortname');
                if (!$course) {
                    error_log('[Data Extractor WARNING] extract_grade_data: Course not found for course ID ' . $grade->courseid);
                    // Continue anyway - some grade items may not have courses
                }
            }

            // Defensive check: ensure grade boundaries are valid
            if (!is_numeric($grade->grademax) || !is_numeric($grade->grademin)) {
                error_log('[Data Extractor ERROR] extract_grade_data: Invalid grade boundaries for grade ID ' . $gradeid);
                return null;
            }

            // Normalize grade to 0-100 scale with proper validation
            $normalized_grade = 0;
            $graderange = $grade->grademax - $grade->grademin;
            if ($graderange > 0) {
                $normalized_grade = (($grade->finalgrade - $grade->grademin) / $graderange) * 100;
                // Clamp to 0-100 range for safety
                $normalized_grade = max(0, min(100, $normalized_grade));
            }

            // Legacy BTEC conversion (0-4 scale → Pass/Merit/Distinction/Refer)
            $btec_grade = $this->convert_to_btec_grade($grade->finalgrade);

            // Build safe payload with all null checks
            $payload = [
                'grade_id' => (int)$grade->id,
                'userid' => (int)$grade->userid,
                'user_username' => $user->username ?? '',
                'user_email' => $user->email ?? '',
                'user_fullname' => fullname($user),
                'itemid' => (int)$grade->itemid,
                'iteminstance' => (int)($grade->iteminstance ?? 0),
                'item_name' => $grade->itemname ?? 'Unnamed Item',
                'item_type' => $grade->itemtype ?? '',
                'item_module' => $grade->itemmodule ?? '',
                'courseid' => (int)($grade->courseid ?? 0),
                'course_name' => $course ? $course->fullname : ($grade->course_name ?? 'N/A'),
                'course_shortname' => $course ? $course->shortname : 'N/A',
                'finalgrade_numeric' => round($normalized_grade, 2), // 0-100 scale
                'finalgrade' => round($normalized_grade, 2), // backward compatibility
                'raw_grade' => (float)$grade->finalgrade,
                'btec_grade' => $btec_grade,
                'grademax' => (float)$grade->grademax,
                'grademin' => (float)$grade->grademin,
                'timecreated' => (int)($grade->timecreated ?? time()),
                'timemodified' => (int)($grade->timemodified ?? time()),
            ];

            // Attach learning outcomes from gradingform_btec
            $payload['learning_outcomes'] = $this->extract_btec_learning_outcomes($grade);

            // Attach grader info using legacy role logic if provided
            if (!empty($grader_userid)) {
                $grader = $DB->get_record('user', ['id' => $grader_userid], 'id,firstname,lastname');
                $payload['grader_userid'] = (int)$grader_userid;
                $payload['grader_fullname'] = $grader ? fullname($grader) : '';
                $payload['grader_role'] = $this->get_grader_role_legacy($grader_userid, (int)($grade->courseid ?? 0));
            }

            return $payload;

        } catch (\Exception $e) {
            error_log('[Data Extractor ERROR] extract_grade_data: Exception - ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Get user's primary role (student or teacher)
     * 
     * @param int $userid
     * @return string 'student', 'teacher', or 'other'
     */
    private function get_user_primary_role($userid) {
        global $DB;

        try {
            // Get user's role assignments
            $sql = "SELECT DISTINCT r.shortname
                    FROM {role_assignments} ra
                    JOIN {role} r ON r.id = ra.roleid
                    WHERE ra.userid = :userid
                    ORDER BY FIELD(r.shortname, 'teacher', 'editingteacher', 'student') DESC
                    LIMIT 1";

            $role = $DB->get_field_sql($sql, ['userid' => $userid]);

            if (!$role) {
                return 'other';
            }

            // Map Moodle roles to simplified roles
            if (in_array($role, ['student', 'learner'])) {
                return 'student';
            } elseif (in_array($role, ['teacher', 'editingteacher', 'instructor'])) {
                return 'teacher';
            } else {
                return 'other';
            }

        } catch (\Exception $e) {
            error_log('[Data Extractor ERROR] get_user_primary_role: ' . $e->getMessage());
            return 'other';
        }
    }

    /**
     * Legacy BTEC grade conversion (0-4 scale -> Pass/Merit/Distinction/Refer)
     * Mirrors legacy observers.php logic verbatim.
     *
     * @param float|null $rawgrade
     * @return string
     */
    private function convert_to_btec_grade($rawgrade) {
        if (is_null($rawgrade)) {
            return 'Refer';
        } elseif ($rawgrade >= 4) {
            return 'Distinction';
        } elseif ($rawgrade >= 3) {
            return 'Merit';
        } elseif ($rawgrade >= 2) {
            return 'Pass';
        }

        return 'Refer';
    }

    /**
     * Extract BTEC learning outcomes from gradingform_btec tables for assignments.
     *
     * @param \stdClass $grade Grade record with itemmodule/iteminstance/userid/courseid
     * @return array
     */
    private function extract_btec_learning_outcomes($grade) {
        global $DB;

        // Only assignments use gradingform_btec
        if (($grade->itemmodule ?? '') !== 'assign') {
            return [];
        }

        // Resolve assignment
        $assignmentid = (int)($grade->iteminstance ?? 0);
        if ($assignmentid <= 0) {
            return [];
        }

        $assignment = $DB->get_record('assign', ['id' => $assignmentid], 'id,course');
        if (!$assignment) {
            return [];
        }

        // Latest assign_grade for this user/assignment (advanced grading itemid references assign_grades.id)
        $assigngrades = $DB->get_records('assign_grades', [
            'assignment' => $assignment->id,
            'userid' => $grade->userid
        ], 'timemodified DESC', '*', 0, 1);

        if (empty($assigngrades)) {
            return [];
        }

        $assigngrade = reset($assigngrades);

        // Find latest grading instance for method btec
        $instance = $DB->get_record_sql(
            "SELECT gi.id, gi.definitionid
               FROM {grading_instances} gi
               JOIN {grading_definitions} gd ON gd.id = gi.definitionid
              WHERE gi.itemid = :itemid AND gd.method = 'btec'
           ORDER BY gi.timemodified DESC", 
            ['itemid' => $assigngrade->id]
        );

        if (!$instance) {
            return [];
        }

        // Criteria definitions
        $criteria = $DB->get_records('gradingform_btec_criteria', [
            'definitionid' => $instance->definitionid
        ], 'sortorder ASC');

        if (empty($criteria)) {
            return [];
        }

        // Detect fillings table dynamically (plugin-specific)
        $fillings = [];
        $tablemanager = $DB->get_manager();
        $fillingtablenames = [
            'gradingform_btec_fillings',
            'gradingform_btec_filling',
        ];

        $fillingtable = null;
        foreach ($fillingtablenames as $candidate) {
            if ($tablemanager->table_exists($candidate)) {
                $fillingtable = $candidate;
                break;
            }
        }

        $fillingsbycriterion = [];
        if ($fillingtable) {
            $fillings = $DB->get_records($fillingtable, ['instanceid' => $instance->id]);
            foreach ($fillings as $filling) {
                if (isset($filling->criterionid)) {
                    $fillingsbycriterion[(int)$filling->criterionid] = $filling;
                }
            }
        }

        $outcomes = [];
        foreach ($criteria as $criterion) {
            $criterionid = (int)$criterion->id;
            $filling = $fillingsbycriterion[$criterionid] ?? null;

            // Determine achieved status using available fields (presence = achieved fallback)
            $achieved = false;
            if ($filling) {
                if (isset($filling->status)) {
                    $achieved = (string)$filling->status === 'achieved' || (int)$filling->status === 1;
                } elseif (isset($filling->levelid)) {
                    $achieved = !empty($filling->levelid);
                } elseif (isset($filling->score)) {
                    $achieved = ((float)$filling->score) > 0;
                } else {
                    $achieved = true; // Filling exists => achieved
                }
            }

            $outcomes[] = [
                'code' => $criterion->shortname,
                'level' => strtoupper(substr($criterion->shortname, 0, 1)),
                'description' => format_string($criterion->description ?? ''),
                'achieved' => (bool)$achieved,
            ];
        }

        return $outcomes;
    }

    /**
     * Legacy grader role detection (priority: IV > Teacher)
     *
     * @param int $grader_userid
     * @param int $courseid
     * @return string 'iv', 'teacher', or 'other'
     */
    private function get_grader_role_legacy($grader_userid, $courseid) {
        if (empty($grader_userid) || empty($courseid)) {
            return 'other';
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return 'other';
        }

        $roles = get_user_roles($context, $grader_userid, false);
        $rolecode = 'other';

        foreach ($roles as $role) {
            if ($role->shortname === 'internalverifier') {
                $rolecode = 'iv';
                break; // IV has priority
            }

            if (in_array($role->shortname, ['teacher', 'editingteacher'])) {
                // Only set if not already iv
                if ($rolecode !== 'iv') {
                    $rolecode = 'teacher';
                }
            }
        }

        return $rolecode;
    }
}
