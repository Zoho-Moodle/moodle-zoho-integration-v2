<?php
/**
 * Event observers for Moodle-Zoho Integration Plugin
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // User created event
    [
        'eventname' => '\\core\\event\\user_created',
        'callback'  => '\\local_moodle_zoho_sync\\observer::user_created',
        'priority'  => 9999,
        'internal'  => false,
    ],

    // User updated event
    [
        'eventname' => '\\core\\event\\user_updated',
        'callback'  => '\\local_moodle_zoho_sync\\observer::user_updated',
        'priority'  => 9999,
        'internal'  => false,
    ],

    // User enrollment created
    [
        'eventname' => '\\core\\event\\user_enrolment_created',
        'callback'  => '\\local_moodle_zoho_sync\\observer::enrollment_created',
        'priority'  => 9999,
        'internal'  => false,
    ],

    // User enrollment deleted (unenrolment)
    [
        'eventname' => '\\core\\event\\user_enrolment_deleted',
        'callback'  => '\\local_moodle_zoho_sync\\observer::enrollment_deleted',
        'priority'  => 9999,
        'internal'  => false,
    ],

    // Assignment submission graded (advanced grading/BTEC)
    [
        'eventname' => '\\mod_assign\\event\\submission_graded',
        'callback'  => '\\local_moodle_zoho_sync\\observer::submission_graded',
        'priority'  => 9999,
        'internal'  => false,
    ],

    // User graded event
    [
        'eventname' => '\\core\\event\\user_graded',
        'callback'  => '\\local_moodle_zoho_sync\\observer::grade_updated',
        'priority'  => 9999,
        'internal'  => false,
    ],
];
