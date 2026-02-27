<?php
/**
 * Admin Dashboard v2 — Universal Navigation
 *
 * Usage:
 *   require_once(__DIR__ . '/nav.php');
 *   mzi2_nav('overview');         // outputs CSS + nav bar
 *   mzi2_breadcrumb('Overview');  // outputs breadcrumb
 *
 * @package   local_moodle_zoho_sync
 * @copyright 2026 ABC Horizon
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Navigation items definition
 */
function mzi2_get_nav_items(): array {
    return [
        'overview' => [
            'label'       => 'Overview',
            'icon'        => 'fa-tachometer',
            'url'         => '/local/moodle_zoho_sync/ui/admin2/overview.php',
            'description' => 'System overview and KPIs',
        ],
        'students' => [
            'label'       => 'Students',
            'icon'        => 'fa-users',
            'url'         => '/local/moodle_zoho_sync/ui/admin2/student_manager.php',
            'description' => 'Search students and manage requests',
        ],
        'events' => [
            'label'       => 'Event Logs',
            'icon'        => 'fa-list-alt',
            'url'         => '/local/moodle_zoho_sync/ui/admin2/event_logs.php',
            'description' => 'Webhook and sync events',
        ],
        'grades' => [
            'label'       => 'Grade Queue',
            'icon'        => 'fa-graduation-cap',
            'url'         => '/local/moodle_zoho_sync/ui/admin2/grade_queue.php',
            'description' => 'Monitor grade sync operations',
        ],
        'health' => [
            'label'       => 'Health',
            'icon'        => 'fa-heartbeat',
            'url'         => '/local/moodle_zoho_sync/ui/admin2/health.php',
            'description' => 'System health checks and statistics',
        ],
        'btec' => [
            'label'       => 'BTEC Templates',
            'icon'        => 'fa-certificate',
            'url'         => '/local/moodle_zoho_sync/ui/admin2/btec.php',
            'description' => 'Manage BTEC grading templates',
        ],
        'settings' => [
            'label'       => 'Settings',
            'icon'        => 'fa-cog',
            'url'         => '/local/moodle_zoho_sync/ui/admin2/settings.php',
            'description' => 'Request windows and plugin configuration',
        ],
    ];
}

/**
 * Render navigation CSS (call once per page, before nav)
 */
function mzi2_nav_css(): void {
    ?>
    <style>
    /* ═══════════════════════════════════════════════════════
       MZI v2 — Admin Dashboard Navigation
       ═══════════════════════════════════════════════════════ */

    .mzi2-wrap {
        max-width: 1600px;
        margin: 0 auto;
    }

    /* ── Top Bar ─────────────────────────────────────────── */
    .mzi2-topbar {
        background: linear-gradient(135deg, #0f2d52 0%, #1a4a7a 100%);
        border-radius: 12px 12px 0 0;
        padding: 18px 28px 0;
        box-shadow: 0 4px 20px rgba(15, 45, 82, 0.35);
        margin-bottom: 0;
    }

    .mzi2-topbar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .mzi2-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .mzi2-brand-icon {
        width: 38px;
        height: 38px;
        background: rgba(255,255,255,0.15);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #fff;
    }

    .mzi2-brand-text {
        color: #fff;
        font-size: 16px;
        font-weight: 700;
        line-height: 1;
    }

    .mzi2-brand-sub {
        color: rgba(255,255,255,0.6);
        font-size: 11px;
        font-weight: 400;
        margin-top: 3px;
        display: block;
    }

    /* Backend Status Pill */
    .mzi2-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }

    .mzi2-status-pill:hover { opacity: 0.85; }

    .mzi2-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .mzi2-pill-unknown  { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.7); }
    .mzi2-pill-checking { background: rgba(255,193,7,0.2);   color: #ffc107; }
    .mzi2-pill-ok       { background: rgba(40,167,69,0.2);   color: #5bd78b; }
    .mzi2-pill-error    { background: rgba(220,53,69,0.2);   color: #f48496; }

    .mzi2-dot-unknown  { background: rgba(255,255,255,0.4); }
    .mzi2-dot-checking { background: #ffc107; animation: mzi2-pulse 1s infinite; }
    .mzi2-dot-ok       { background: #28a745; }
    .mzi2-dot-error    { background: #dc3545; }

    @keyframes mzi2-pulse {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.3; }
    }

    /* ── Nav Tabs ────────────────────────────────────────── */
    .mzi2-nav-tabs {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 2px;
    }

    .mzi2-nav-tabs li { margin: 0; }

    .mzi2-nav-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 11px 18px;
        color: rgba(255,255,255,0.65);
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        border-radius: 8px 8px 0 0;
        transition: all 0.2s;
        white-space: nowrap;
        position: relative;
    }

    .mzi2-nav-link i { font-size: 14px; }

    .mzi2-nav-link:hover,
    .mzi2-nav-link:focus {
        color: #fff;
        background: rgba(255,255,255,0.1);
        text-decoration: none;
    }

    .mzi2-nav-link.active {
        color: #fff;
        background: rgba(255,255,255,0.15);
        font-weight: 600;
    }

    .mzi2-nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: #4fc3f7;
        border-radius: 2px 2px 0 0;
    }

    /* ── Content Area ────────────────────────────────────── */
    .mzi2-content {
        background: #f5f7fb;
        border-radius: 0 0 12px 12px;
        padding: 28px;
        min-height: 500px;
    }

    /* ── Breadcrumb ──────────────────────────────────────── */
    .mzi2-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        margin-bottom: 22px;
        color: #6c757d;
    }

    .mzi2-breadcrumb a {
        color: #1a4a7a;
        text-decoration: none;
    }

    .mzi2-breadcrumb a:hover { text-decoration: underline; }
    .mzi2-breadcrumb-sep { color: #ced4da; }
    .mzi2-breadcrumb-cur { color: #343a40; font-weight: 600; }

    /* ── Shared Card ─────────────────────────────────────── */
    .mzi2-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07), 0 4px 16px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    .mzi2-card-header {
        padding: 16px 22px;
        border-bottom: 1px solid #eef0f4;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .mzi2-card-title {
        font-size: 14px;
        font-weight: 700;
        color: #1a2e4a;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .mzi2-card-body { padding: 20px 22px; }

    /* ── KPI Grid ────────────────────────────────────────── */
    .mzi2-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .mzi2-kpi-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        border-left: 4px solid #dee2e6;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .mzi2-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    }

    .mzi2-kpi-card.kpi-blue   { border-left-color: #007bff; }
    .mzi2-kpi-card.kpi-green  { border-left-color: #28a745; }
    .mzi2-kpi-card.kpi-red    { border-left-color: #dc3545; }
    .mzi2-kpi-card.kpi-orange { border-left-color: #fd7e14; }
    .mzi2-kpi-card.kpi-teal   { border-left-color: #17a2b8; }
    .mzi2-kpi-card.kpi-purple { border-left-color: #6f42c1; }

    .mzi2-kpi-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        margin-bottom: 14px;
    }

    .kpi-blue   .mzi2-kpi-icon { background: #e8f0fe; color: #007bff; }
    .kpi-green  .mzi2-kpi-icon { background: #e6f4ea; color: #28a745; }
    .kpi-red    .mzi2-kpi-icon { background: #fce8e8; color: #dc3545; }
    .kpi-orange .mzi2-kpi-icon { background: #fff3e0; color: #fd7e14; }
    .kpi-teal   .mzi2-kpi-icon { background: #e0f7f9; color: #17a2b8; }
    .kpi-purple .mzi2-kpi-icon { background: #f0ebfc; color: #6f42c1; }

    .mzi2-kpi-value {
        font-size: 32px;
        font-weight: 700;
        color: #1a2e4a;
        line-height: 1;
        margin-bottom: 6px;
    }

    .mzi2-kpi-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .mzi2-kpi-sub {
        font-size: 11px;
        color: #adb5bd;
        margin-top: 4px;
    }

    /* ── Buttons ─────────────────────────────────────────── */
    .mzi2-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }

    .mzi2-btn:hover { text-decoration: none; opacity: 0.88; }
    .mzi2-btn:disabled { opacity: 0.55; cursor: not-allowed; }

    .mzi2-btn-primary  { background: #007bff; color: #fff; }
    .mzi2-btn-success  { background: #28a745; color: #fff; }
    .mzi2-btn-danger   { background: #dc3545; color: #fff; }
    .mzi2-btn-warning  { background: #fd7e14; color: #fff; }
    .mzi2-btn-outline  { background: #fff; color: #495057; border: 1px solid #dee2e6; }
    .mzi2-btn-outline:hover { background: #f8f9fa; }
    .mzi2-btn-sm { padding: 6px 12px; font-size: 12px; }

    /* ── Quick Actions Grid ──────────────────────────────── */
    .mzi2-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
    }

    .mzi2-action-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        background: #fff;
        border: 1px solid #eef0f4;
        border-radius: 9px;
        text-decoration: none;
        color: #343a40;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        cursor: pointer;
    }

    .mzi2-action-btn:hover,
    .mzi2-action-btn:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        color: #007bff;
        text-decoration: none;
    }

    .mzi2-action-icon {
        width: 34px;
        height: 34px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    /* ── Alert Banner ────────────────────────────────────── */
    .mzi2-alert {
        padding: 12px 18px;
        border-radius: 8px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .mzi2-alert-warning { background: #fff8e6; border: 1px solid #ffe0a0; color: #7d4e00; }
    .mzi2-alert-danger  { background: #fef0f0; border: 1px solid #f5c6cb; color: #721c24; }
    .mzi2-alert-info    { background: #e8f4fd; border: 1px solid #bee5eb; color: #0c5460; }
    .mzi2-alert-success { background: #e9f7ef; border: 1px solid #c3e6cb; color: #155724; }

    /* ── Table ───────────────────────────────────────────── */
    .mzi2-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .mzi2-table th {
        padding: 10px 14px;
        background: #f8f9fa;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #495057;
        border-bottom: 2px solid #eef0f4;
        text-align: left;
    }

    .mzi2-table td {
        padding: 11px 14px;
        border-bottom: 1px solid #f1f3f5;
        vertical-align: middle;
        color: #343a40;
    }

    .mzi2-table tr:last-child td { border-bottom: none; }
    .mzi2-table tr:hover td { background: #fafbff; }

    /* ── Status Badges ───────────────────────────────────── */
    .mzi2-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .mzi2-badge-sent     { background: #e6f4ea; color: #1e7e34; }
    .mzi2-badge-failed   { background: #fce8e8; color: #b71c1c; }
    .mzi2-badge-pending  { background: #fff8e6; color: #7d4e00; }
    .mzi2-badge-retrying { background: #e8f0fe; color: #1a56db; }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 768px) {
        .mzi2-nav-tabs { flex-wrap: wrap; }
        .mzi2-content { padding: 18px; }
        .mzi2-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        .mzi2-topbar-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        .mzi2-nav-link { padding: 9px 12px; font-size: 12px; }
    }
    </style>
    <?php
}

/**
 * Render the nav bar
 *
 * @param string $active  Key of the current page (see mzi2_get_nav_items())
 */
function mzi2_nav(string $active = 'overview'): void {
    mzi2_nav_css();
    $items = mzi2_get_nav_items();
    ?>
    <div class="mzi2-wrap">
        <div class="mzi2-topbar">
            <!-- Brand + Backend Status -->
            <div class="mzi2-topbar-header">
                <div class="mzi2-brand">
                    <div class="mzi2-brand-icon">
                        <i class="fa fa-refresh"></i>
                    </div>
                    <div>
                        <div class="mzi2-brand-text">Moodle–Zoho Sync</div>
                        <span class="mzi2-brand-sub">Admin Dashboard v2</span>
                    </div>
                </div>

                <!-- Backend status pill (AJAX-driven) -->
                <button id="mzi2-backend-pill"
                        class="mzi2-status-pill mzi2-pill-unknown"
                        onclick="mzi2CheckBackend()"
                        title="Click to test backend connection">
                    <span class="mzi2-status-dot mzi2-dot-unknown" id="mzi2-status-dot"></span>
                    <span id="mzi2-status-label">Backend: click to check</span>
                </button>
            </div>

            <!-- Navigation tabs -->
            <ul class="mzi2-nav-tabs">
                <?php foreach ($items as $key => $item): ?>
                    <li>
                        <a href="<?php echo (new moodle_url($item['url']))->out(); ?>"
                           class="mzi2-nav-link <?php echo $key === $active ? 'active' : ''; ?>"
                           title="<?php echo s($item['description']); ?>">
                            <i class="fa <?php echo $item['icon']; ?>"></i>
                            <?php echo s($item['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="mzi2-content">
    <?php
    // JS for backend pill
    ?>
    <script>
    async function mzi2CheckBackend() {
        const pill  = document.getElementById('mzi2-backend-pill');
        const dot   = document.getElementById('mzi2-status-dot');
        const label = document.getElementById('mzi2-status-label');

        pill.className  = 'mzi2-status-pill mzi2-pill-checking';
        dot.className   = 'mzi2-status-dot mzi2-dot-checking';
        label.textContent = 'Checking...';

        try {
            const resp = await fetch(M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax/test_connection.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'sesskey=' + M.cfg.sesskey,
            });
            const data = await resp.json();

            if (data.success) {
                pill.className  = 'mzi2-status-pill mzi2-pill-ok';
                dot.className   = 'mzi2-status-dot mzi2-dot-ok';
                label.textContent = 'Backend: Online';
            } else {
                throw new Error(data.message || 'Error');
            }
        } catch (e) {
            pill.className  = 'mzi2-status-pill mzi2-pill-error';
            dot.className   = 'mzi2-status-dot mzi2-dot-error';
            label.textContent = 'Backend: Offline';
            pill.title = e.message;
        }
    }
    </script>
    <?php
}

/**
 * Close the content div opened by mzi2_nav()
 */
function mzi2_nav_close(): void {
    echo '</div>'; // .mzi2-content
    echo '</div>'; // .mzi2-wrap
}

/**
 * Render breadcrumb inside content area
 *
 * @param string $current  Display name of current page
 */
function mzi2_breadcrumb(string $current): void {
    $admin_url   = new moodle_url('/admin/index.php');
    $dash_url    = new moodle_url('/local/moodle_zoho_sync/ui/admin2/overview.php');
    ?>
    <div class="mzi2-breadcrumb">
        <a href="<?php echo $admin_url->out(); ?>">
            <i class="fa fa-home"></i> Site Admin
        </a>
        <span class="mzi2-breadcrumb-sep">›</span>
        <a href="<?php echo $dash_url->out(); ?>">Moodle–Zoho Sync</a>
        <span class="mzi2-breadcrumb-sep">›</span>
        <span class="mzi2-breadcrumb-cur"><?php echo s($current); ?></span>
    </div>
    <?php
}
