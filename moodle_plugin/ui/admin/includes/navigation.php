<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Universal Navigation Component - Shared across all UI pages
 * Provides consistent navigation bar, breadcrumbs, and styling
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No direct access check needed - this file is included by admin pages that already verify access

/**
 * Render the universal navigation bar
 * 
 * @param string $current_page The key of the current page
 * @param string $page_title The title to display in the nav header
 * @param string $page_subtitle Optional subtitle
 */
function mzi_render_navigation($current_page = 'dashboard', $page_title = 'Moodle-Zoho Integration', $page_subtitle = '') {
    $nav_items = [
        [
            'name' => 'Dashboard',
            'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/dashboard.php'),
            'icon' => '🏠',
            'key' => 'dashboard',
            'description' => 'System overview and quick stats'
        ],
        [
            'name' => 'Grade Monitor',
            'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/grade_queue_monitor.php'),
            'icon' => '📊',
            'key' => 'grade_monitor',
            'description' => 'Monitor grade sync operations'
        ],
        [
            'name' => 'Event Logs',
            'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'),
            'icon' => '📋',
            'key' => 'event_logs',
            'description' => 'View webhook and sync events'
        ],
        [
            'name' => 'Health Check',
            'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/health_check.php'),
            'icon' => '💊',
            'key' => 'health_check',
            'description' => 'System health and connectivity'
        ],
        [
            'name' => 'Statistics',
            'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/statistics.php'),
            'icon' => '📈',
            'key' => 'statistics',
            'description' => 'Detailed analytics and reports'
        ],
        [
            'name' => 'BTEC Templates',
            'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/btec_templates.php'),
            'icon' => '📝',
            'key' => 'btec_templates',
            'description' => 'Manage BTEC grading templates'
        ],
        [
            'name' => 'Sync Management',
            'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/sync_management.php'),
            'icon' => '⚙️',
            'key' => 'sync_management',
            'description' => 'Manage sync operations'
        ],
    ];
    
    ?>
    <nav class="mzi-nav-bar">
        <div class="mzi-nav-header">
            <div>
                <h1 class="mzi-nav-title">
                    🔄 <?php echo $page_title; ?>
                </h1>
                <?php if ($page_subtitle): ?>
                    <p class="mzi-nav-subtitle"><?php echo $page_subtitle; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <ul class="mzi-nav-items">
            <?php foreach ($nav_items as $item): ?>
                <li class="mzi-nav-item">
                    <a href="<?php echo $item['url']->out(); ?>" 
                       class="mzi-nav-link <?php echo $item['key'] === $current_page ? 'active' : ''; ?>"
                       title="<?php echo $item['description']; ?>">
                        <span class="mzi-nav-icon"><?php echo $item['icon']; ?></span>
                        <span class="mzi-nav-label"><?php echo $item['name']; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php
}

/**
 * Render breadcrumb navigation
 * 
 * @param string $current_page_name The name of the current page
 */
function mzi_render_breadcrumb($current_page_name) {
    ?>
    <div class="mzi-breadcrumb">
        <a href="<?php echo new moodle_url('/admin/index.php'); ?>">🏠 Site Administration</a>
        <span class="mzi-breadcrumb-separator">›</span>
        <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/dashboard.php'); ?>">Moodle-Zoho Sync</a>
        <span class="mzi-breadcrumb-separator">›</span>
        <span class="mzi-breadcrumb-current"><?php echo $current_page_name; ?></span>
    </div>
    <?php
}

/**
 * Output the universal CSS styles
 * Should be called once in the <head> or after header
 */
function mzi_output_navigation_styles() {
    ?>
    <style>
    /* ══════════════════════════════════════════════════════════
       UNIVERSAL NAVIGATION BAR - Used across all UI pages
       ══════════════════════════════════════════════════════════ */

    .mzi-page-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0;
    }

    .mzi-nav-bar {
        background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
        border-radius: 12px;
        padding: 0;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(30, 58, 95, 0.3);
        overflow: hidden;
    }

    .mzi-nav-header {
        padding: 20px 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .mzi-nav-title {
        color: white;
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .mzi-nav-subtitle {
        color: rgba(255, 255, 255, 0.8);
        font-size: 13px;
        margin-top: 4px;
    }

    .mzi-nav-items {
        display: flex;
        padding: 0;
        margin: 0;
        list-style: none;
    }

    .mzi-nav-item {
        flex: 1;
    }

    .mzi-nav-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 20px 15px;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        transition: all 0.3s ease;
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
        overflow: hidden;
    }

    .mzi-nav-item:last-child .mzi-nav-link {
        border-right: none;
    }

    .mzi-nav-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: white;
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .mzi-nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .mzi-nav-link:hover::before {
        transform: scaleX(1);
    }

    .mzi-nav-link.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        font-weight: 600;
    }

    .mzi-nav-link.active::before {
        transform: scaleX(1);
    }

    .mzi-nav-icon {
        font-size: 24px;
        line-height: 1;
    }

    .mzi-nav-label {
        font-size: 13px;
        font-weight: 500;
        text-align: center;
        white-space: nowrap;
    }

    /* Breadcrumb */
    .mzi-breadcrumb {
        background: white;
        padding: 15px 25px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .mzi-breadcrumb a {
        color: #007bff;
        text-decoration: none;
        transition: all 0.2s;
    }

    .mzi-breadcrumb a:hover {
        text-decoration: underline;
        color: #0056b3;
    }

    .mzi-breadcrumb-separator {
        color: #6c757d;
    }

    .mzi-breadcrumb-current {
        color: #495057;
        font-weight: 600;
    }

    /* Back Button */
    .mzi-back-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: white;
        color: #495057;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        margin-bottom: 20px;
    }

    .mzi-back-button:hover {
        background: #f8f9fa;
        border-color: #adb5bd;
        color: #212529;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Page Content Wrapper */
    .mzi-content-wrapper {
        background: transparent;
        padding: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .mzi-nav-items {
            flex-direction: column;
        }
        
        .mzi-nav-link {
            border-right: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .mzi-nav-item:last-child .mzi-nav-link {
            border-bottom: none;
        }
        
        .mzi-breadcrumb {
            font-size: 12px;
            padding: 12px 15px;
            flex-wrap: wrap;
        }
    }
    </style>
    <?php
}
