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
 * Student dashboard for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

// Require login only - any authenticated user can view their own dashboard
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/dashboard/student.php'));
$PAGE->set_title(get_string('dashboard_title', 'local_moodle_zoho_sync'));
$PAGE->set_heading(get_string('dashboard_title', 'local_moodle_zoho_sync'));
$PAGE->set_pagelayout('standard');

// Add CSS.
$PAGE->requires->css('/local/moodle_zoho_sync/assets/css/dashboard.css');

// Add JavaScript - Enhanced version with better UX.
$PAGE->requires->js('/local/moodle_zoho_sync/assets/js/dashboard_enhanced.js');
$PAGE->requires->js_init_call('dashboard.init', array($USER->id));

echo $OUTPUT->header();

?>

<div class="moodle-zoho-dashboard">
    <div class="dashboard-header">
        <h2><?php echo get_string('dashboard_welcome', 'local_moodle_zoho_sync', fullname($USER)); ?></h2>
        <p class="dashboard-subtitle"><?php echo get_string('dashboard_subtitle', 'local_moodle_zoho_sync'); ?></p>
        
        <!-- Quick Summary Section -->
        <div class="dashboard-quick-summary">
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-info" style="border-left: 4px solid #0f6cbf;">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-info-circle fa-2x mr-3"></i>
                            <div>
                                <strong><?php echo get_string('quick_summary', 'local_moodle_zoho_sync'); ?></strong><br>
                                <span class="text-muted"><?php echo get_string('quick_summary_help', 'local_moodle_zoho_sync'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs dashboard-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">
                <i class="icon fa fa-user"></i>
                <?php echo get_string('profile_tab', 'local_moodle_zoho_sync'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="academics-tab" data-toggle="tab" href="#academics" role="tab">
                <i class="icon fa fa-book"></i>
                <?php echo get_string('academics_tab', 'local_moodle_zoho_sync'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="finance-tab" data-toggle="tab" href="#finance" role="tab">
                <i class="icon fa fa-credit-card"></i>
                <?php echo get_string('finance_tab', 'local_moodle_zoho_sync'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="classes-tab" data-toggle="tab" href="#classes" role="tab">
                <i class="icon fa fa-calendar"></i>
                <?php echo get_string('classes_tab', 'local_moodle_zoho_sync'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="grades-tab" data-toggle="tab" href="#grades" role="tab">
                <i class="icon fa fa-graduation-cap"></i>
                <?php echo get_string('grades_tab', 'local_moodle_zoho_sync'); ?>
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content dashboard-content">
        
        <!-- Profile Tab -->
        <div class="tab-pane fade show active" id="profile" role="tabpanel">
            <div class="loading-spinner" id="profile-loader">
                <i class="fa fa-spinner fa-spin"></i> <?php echo get_string('loading', 'local_moodle_zoho_sync'); ?>
            </div>
            <div class="profile-content" id="profile-content" style="display: none;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>

        <!-- Academics Tab -->
        <div class="tab-pane fade" id="academics" role="tabpanel">
            <div class="loading-spinner" id="academics-loader">
                <i class="fa fa-spinner fa-spin"></i> <?php echo get_string('loading', 'local_moodle_zoho_sync'); ?>
            </div>
            <div class="academics-content" id="academics-content" style="display: none;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>

        <!-- Finance Tab -->
        <div class="tab-pane fade" id="finance" role="tabpanel">
            <div class="loading-spinner" id="finance-loader">
                <i class="fa fa-spinner fa-spin"></i> <?php echo get_string('loading', 'local_moodle_zoho_sync'); ?>
            </div>
            <div class="finance-content" id="finance-content" style="display: none;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>

        <!-- Classes Tab -->
        <div class="tab-pane fade" id="classes" role="tabpanel">
            <div class="loading-spinner" id="classes-loader">
                <i class="fa fa-spinner fa-spin"></i> <?php echo get_string('loading', 'local_moodle_zoho_sync'); ?>
            </div>
            <div class="classes-content" id="classes-content" style="display: none;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>

        <!-- Grades Tab -->
        <div class="tab-pane fade" id="grades" role="tabpanel">
            <div class="loading-spinner" id="grades-loader">
                <i class="fa fa-spinner fa-spin"></i> <?php echo get_string('loading', 'local_moodle_zoho_sync'); ?>
            </div>
            <div class="grades-content" id="grades-content" style="display: none;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>

    </div>
</div>

<?php

echo $OUTPUT->footer();
