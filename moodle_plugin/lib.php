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
 * Library functions for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the global navigation tree by adding Moodle-Zoho Integration nodes.
 *
 * @param global_navigation $navigation The global navigation tree
 */
function local_moodle_zoho_sync_extend_navigation(global_navigation $navigation) {
    global $USER, $PAGE;

    // Only add navigation for logged-in users.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Add Student Area navigation for students.
    if (has_capability('local/moodle_zoho_sync:viewdashboard', context_system::instance())) {
        // Add main Student Area container node.
        $studentnode = $navigation->add(
            get_string('mystudentarea', 'local_moodle_zoho_sync'),
            null,
            navigation_node::TYPE_CONTAINER,
            null,
            'moodle_zoho_student',
            new pix_icon('i/user', '')
        );
        $studentnode->showinflatnavigation = true;

        // Add Profile page.
        $profilenode = $studentnode->add(
            get_string('studentprofile', 'local_moodle_zoho_sync'),
            new moodle_url('/local/moodle_zoho_sync/ui/student/profile.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'student_profile',
            new pix_icon('i/user', '')
        );
        
        // Add My Programs page.
        $programsnode = $studentnode->add(
            get_string('myprograms', 'local_moodle_zoho_sync'),
            new moodle_url('/local/moodle_zoho_sync/ui/student/programs.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'student_programs',
            new pix_icon('i/badge', '')
        );
        
        // Add My Classes page.
        $classesnode = $studentnode->add(
            get_string('myclasses', 'local_moodle_zoho_sync'),
            new moodle_url('/local/moodle_zoho_sync/ui/student/classes.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'student_classes',
            new pix_icon('i/course', '')
        );
        
        // Add My Grades page.
        $gradesnode = $studentnode->add(
            get_string('mygrades', 'local_moodle_zoho_sync'),
            new moodle_url('/local/moodle_zoho_sync/ui/student/grades.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'student_grades',
            new pix_icon('i/scales', '')
        );

        // Add My Requests page.
        $requestsnode = $studentnode->add(
            get_string('myrequests', 'local_moodle_zoho_sync'),
            new moodle_url('/local/moodle_zoho_sync/ui/student/requests.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'student_requests',
            new pix_icon('i/edit', '')
        );
        
        // Add Student Card page.
        $cardnode = $studentnode->add(
            get_string('studentcard', 'local_moodle_zoho_sync'),
            new moodle_url('/local/moodle_zoho_sync/ui/student/student_card.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'student_card',
            new pix_icon('i/identity', '')
        );
    }
}

/**
 * Extend the settings navigation tree.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The context of the page
 */
function local_moodle_zoho_sync_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;

    // Only add to course pages.
    if ($PAGE->course->id == SITEID) {
        return;
    }

    // Check if user has management capability.
    if (!has_capability('local/moodle_zoho_sync:manage', $context)) {
        return;
    }

    // Add sync management link to course administration.
    if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
        $url = new moodle_url('/local/moodle_zoho_sync/ui/admin2/overview.php');
        
        $node = navigation_node::create(
            get_string('sync_management', 'local_moodle_zoho_sync'),
            $url,
            navigation_node::NODETYPE_LEAF,
            'moodle_zoho_sync',
            'moodle_zoho_sync',
            new pix_icon('i/reload', '')
        );

        if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
            $node->make_active();
        }

        $settingnode->add_node($node);
    }
}

/**
 * Serve the files from the plugin file areas.
 *
 * @param stdClass $course The course object
 * @param stdClass $cm The course module object
 * @param context $context The context
 * @param string $filearea The name of the file area
 * @param array $args Extra arguments
 * @param bool $forcedownload Whether to force download
 * @param array $options Additional options
 * @return bool False if file not found, does not return otherwise
 */
function local_moodle_zoho_sync_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // Check capabilities.
    if (!has_capability('local/moodle_zoho_sync:viewdashboard', $context)) {
        return false;
    }

    // No file areas defined yet.
    return false;
}
