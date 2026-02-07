<?php
/**
 * Admin settings for Moodle-Zoho Integration Plugin
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/admin_setting_encrypted_token.php');

if ($hassiteconfig) {
    // Create settings page.
    $settings = new admin_settingpage('local_moodle_zoho_sync', 
        get_string('pluginname', 'local_moodle_zoho_sync'));
    
    // Create category for plugin.
    $ADMIN->add('localplugins', new admin_category('local_moodle_zoho_sync_category',
        get_string('pluginname', 'local_moodle_zoho_sync')));
    
    // Add settings page.
    $ADMIN->add('local_moodle_zoho_sync_category', $settings);
    
    // Add Dashboard (first in list).
    $ADMIN->add('local_moodle_zoho_sync_category', new admin_externalpage(
        'local_moodle_zoho_sync_dashboard',
        get_string('admin_dashboard', 'local_moodle_zoho_sync'),
        new moodle_url('/local/moodle_zoho_sync/ui/admin/dashboard.php'),
        'local/moodle_zoho_sync:manage'
    ));
    
    // Add management pages.
    $ADMIN->add('local_moodle_zoho_sync_category', new admin_externalpage(
        'local_moodle_zoho_sync_logs',
        get_string('event_logs', 'local_moodle_zoho_sync'),
        new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'),
        'local/moodle_zoho_sync:viewlogs'
    ));
    
    $ADMIN->add('local_moodle_zoho_sync_category', new admin_externalpage(
        'local_moodle_zoho_sync_statistics',
        get_string('statistics', 'local_moodle_zoho_sync'),
        new moodle_url('/local/moodle_zoho_sync/ui/admin/statistics.php'),
        'local/moodle_zoho_sync:viewlogs'
    ));
    
    $ADMIN->add('local_moodle_zoho_sync_category', new admin_externalpage(
        'local_moodle_zoho_sync_health',
        get_string('health_check', 'local_moodle_zoho_sync'),
        new moodle_url('/local/moodle_zoho_sync/ui/admin/health_check.php'),
        'local/moodle_zoho_sync:manage'
    ));

    // Backend API URL
    $settings->add(new admin_setting_configtext(
        'local_moodle_zoho_sync/backend_url',
        get_string('backend_url', 'local_moodle_zoho_sync'),
        get_string('backend_url_desc', 'local_moodle_zoho_sync'),
        'http://localhost:8001',
        PARAM_URL
    ));

    // API Token - ENCRYPTED STORAGE (SECURITY CRITICAL)
    // This custom setting stores tokens ENCRYPTED in local_mzi_config, NOT in mdl_config_plugins
    $settings->add(new admin_setting_encrypted_token(
        'local_moodle_zoho_sync/api_token',
        get_string('api_token', 'local_moodle_zoho_sync'),
        get_string('api_token_desc', 'local_moodle_zoho_sync') . 
            '<br><strong>🔒 Security:</strong> Token is stored encrypted using AES-256-CBC. Never stored in plain text.',
        ''
    ));

    // Enable User Sync
    $settings->add(new admin_setting_configcheckbox(
        'local_moodle_zoho_sync/enable_user_sync',
        get_string('enable_user_sync', 'local_moodle_zoho_sync'),
        get_string('enable_user_sync_desc', 'local_moodle_zoho_sync'),
        1
    ));

    // Enable Enrollment Sync
    $settings->add(new admin_setting_configcheckbox(
        'local_moodle_zoho_sync/enable_enrollment_sync',
        get_string('enable_enrollment_sync', 'local_moodle_zoho_sync'),
        get_string('enable_enrollment_sync_desc', 'local_moodle_zoho_sync'),
        1
    ));

    // Enable Grade Sync
    $settings->add(new admin_setting_configcheckbox(
        'local_moodle_zoho_sync/enable_grade_sync',
        get_string('enable_grade_sync', 'local_moodle_zoho_sync'),
        get_string('enable_grade_sync_desc', 'local_moodle_zoho_sync'),
        1
    ));

    // Enable Debug Logging
    $settings->add(new admin_setting_configcheckbox(
        'local_moodle_zoho_sync/enable_debug',
        get_string('enable_debug', 'local_moodle_zoho_sync'),
        get_string('enable_debug_desc', 'local_moodle_zoho_sync'),
        0
    ));

    // SSL Verification - WITH PROMINENT SECURITY WARNING
    $ssl_warning_html = '<div class="alert alert-warning mt-2" style="margin-top:10px;padding:15px;background:#fff3cd;border:2px solid #ffc107;border-radius:6px;">' .
        '<h5 style="margin-top:0;color:#856404;"><i class="fa fa-exclamation-triangle"></i> <strong>SECURITY WARNING</strong></h5>' .
        '<p style="margin-bottom:0;"><strong>Disabling SSL verification exposes your system to Man-in-the-Middle attacks.</strong></p>' .
        '<ul style="margin:10px 0 0 20px;">' .
        '<li><strong style="color:#d9534f;">DO NOT</strong> disable in production environments</li>' .
        '<li>Only use for local development with self-signed certificates</li>' .
        '<li>Ensure your backend uses valid SSL certificates in production</li>' .
        '</ul>' .
        '</div>';
    
    $settings->add(new admin_setting_configcheckbox(
        'local_moodle_zoho_sync/ssl_verify',
        get_string('ssl_verify', 'local_moodle_zoho_sync') . ' <span class="badge badge-danger">CRITICAL</span>',
        get_string('ssl_verify_desc', 'local_moodle_zoho_sync') . $ssl_warning_html,
        1
    ));
    
    // Max retry attempts.
    $settings->add(new admin_setting_configtext(
        'local_moodle_zoho_sync/max_retry_attempts',
        get_string('max_retry_attempts', 'local_moodle_zoho_sync') . 
            ' <i class="fa fa-info-circle" title="' . get_string('max_retry_help', 'local_moodle_zoho_sync') . '"></i>',
        get_string('max_retry_attempts_desc', 'local_moodle_zoho_sync'),
        3,
        PARAM_INT
    ));
    
    // Log retention days.
    $settings->add(new admin_setting_configtext(
        'local_moodle_zoho_sync/log_retention_days',
        get_string('log_retention_days', 'local_moodle_zoho_sync') . 
            ' <i class="fa fa-info-circle" title="' . get_string('log_retention_help', 'local_moodle_zoho_sync') . '"></i>',
        get_string('log_retention_days_desc', 'local_moodle_zoho_sync'),
        30,
        PARAM_INT
    ));
}
