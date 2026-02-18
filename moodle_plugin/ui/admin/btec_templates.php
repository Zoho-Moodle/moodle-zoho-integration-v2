<?php
/**
 * BTEC Templates Management Page
 *
 * Displays synced BTEC templates and provides sync controls with detailed stats.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/navigation.php');

admin_externalpage_setup('local_moodle_zoho_sync_btec_templates');

$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/btec_templates.php'));
$PAGE->set_title(get_string('pluginname', 'local_moodle_zoho_sync') . ' - BTEC Templates');
$PAGE->set_heading(get_string('pluginname', 'local_moodle_zoho_sync') . ' - BTEC Templates');

// Get Backend URL
$backend_url = get_config('local_moodle_zoho_sync', 'backend_url');

echo $OUTPUT->header();

mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('btec_templates', 'BTEC Templates', 'Manage BTEC grading templates and sync with Zoho');
mzi_render_breadcrumb('BTEC Templates');
echo '<div class="mzi-content-wrapper">';

echo $OUTPUT->heading('BTEC Grading Templates Management', 2);

// Get statistics
global $DB;
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists(new xmldb_table('local_mzi_btec_templates'));

$total_templates = 0;
$last_sync_time = null;
$templates_with_criteria = 0;

if ($table_exists) {
    $total_templates = $DB->count_records('local_mzi_btec_templates');
    $last_sync = $DB->get_record_sql("
        SELECT MAX(synced_at) as last_sync
        FROM {local_mzi_btec_templates}
    ");
    $last_sync_time = $last_sync->last_sync ?? null;
    
    // Count templates with active definitions
    $templates_with_criteria = $DB->count_records_sql("
        SELECT COUNT(DISTINCT t.id)
        FROM {local_mzi_btec_templates} t
        INNER JOIN {grading_definitions} d ON t.definition_id = d.id
        WHERE d.status = 20
    ");
}

// Summary Cards
echo '<div class="row mb-4">';

// Total Templates
echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h5 class="card-title">Total Templates</h5>';
echo '<h2 class="display-4">' . $total_templates . '</h2>';
echo '<p class="text-muted">Synced from Zoho</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Active Templates
echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h5 class="card-title">Ready</h5>';
echo '<h2 class="display-4 text-success">' . $templates_with_criteria . '</h2>';
echo '<p class="text-muted">With criteria</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Draft/Inactive
$inactive_count = $total_templates - $templates_with_criteria;
echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h5 class="card-title">Draft/Inactive</h5>';
echo '<h2 class="display-4 text-warning">' . $inactive_count . '</h2>';
echo '<p class="text-muted">Pending setup</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Last Sync
echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h5 class="card-title">Last Sync</h5>';
if ($last_sync_time) {
    echo '<p class="h6 text-info">' . userdate($last_sync_time, '%d %b %Y') . '</p>';
    echo '<p class="small text-muted">' . userdate($last_sync_time, '%H:%M') . '</p>';
} else {
    echo '<p class="text-muted">Never</p>';
}
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row

// Display info box
echo '<div class="alert alert-info">';
echo '<h5><i class="fa fa-info-circle"></i> About BTEC Templates</h5>';
echo '<p>BTEC grading templates are fetched from Zoho BTEC Module and converted into Moodle grading definitions. Each template contains grading criteria organized by achievement level:</p>';
echo '<ul class="mb-0">';
echo '<li><strong>Pass (P):</strong> P1-P20 criteria</li>';
echo '<li><strong>Merit (M):</strong> M1-M8 criteria</li>';
echo '<li><strong>Distinction (D):</strong> D1-D6 criteria</li>';
echo '</ul>';
echo '</div>';

// Sync controls
echo '<div class="card mb-4">';
echo '<div class="card-header bg-primary text-white">';
echo '<h5 class="mb-0"><i class="fa fa-sync"></i> Sync Controls</h5>';
echo '</div>';
echo '<div class="card-body">';
echo '<div class="row">';
echo '<div class="col-md-8">';
echo '<h6>Sync from Zoho</h6>';
echo '<p>Fetches all BTEC units from Zoho with valid P1 criteria. Updates existing templates and creates new ones.</p>';
echo '<ul class="small">';
echo '<li>✅ Updates existing templates with latest criteria</li>';
echo '<li>✅ Creates new templates for newly added units</li>';
echo '<li>✅ Preserves definition IDs for templates in use</li>';
echo '<li>⚠️ Only syncs units with non-empty P1_description</li>';
echo '</ul>';
echo '</div>';
echo '<div class="col-md-4 text-center">';
echo '<button id="syncButton" class="btn btn-primary mb-2" style="min-width: 160px;">';
echo '<i class="fa fa-sync"></i> Sync All Templates';
echo '</button>';
echo '<br>';
echo '<a href="' . $backend_url . '/api/v1/btec/templates" target="_blank" class="btn btn-sm btn-outline-secondary mt-2">';
echo '<i class="fa fa-list"></i> View API';
echo '</a>';
echo '<div id="syncProgress" class="mt-3" style="display: none;">';
echo '<div class="progress" style="height: 25px;">';
echo '<div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;">0%</div>';
echo '</div>';
echo '<p id="syncStatus" class="small text-muted mt-2">Initializing...</p>';
echo '</div>';
echo '<div id="syncResult" class="alert mt-3" style="display: none;"></div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Display synced templates
if ($table_exists) {
    $templates = $DB->get_records_sql("
        SELECT 
            t.*,
            d.name as definition_name,
            d.status as definition_status,
            d.areaid,
            (SELECT COUNT(*) FROM {gradingform_btec_criteria} WHERE definitionid = t.definition_id) as criteria_count
        FROM {local_mzi_btec_templates} t
        LEFT JOIN {grading_definitions} d ON t.definition_id = d.id
        ORDER BY t.synced_at DESC
    ");

    if ($templates) {
        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h5 class="mb-0">Synced Templates (' . count($templates) . ')</h5>';
        echo '</div>';
        echo '<div class="card-body p-0">';
        
        // Search/Filter
        echo '<div class="p-3 border-bottom">';
        echo '<div class="row">';
        echo '<div class="col-md-8">';
        echo '<input type="text" id="templateSearch" class="form-control" placeholder="🔍 Search by unit name or Zoho ID...">';
        echo '</div>';
        echo '<div class="col-md-4">';
        echo '<select id="statusFilter" class="form-control">';
        echo '<option value="all">All Status</option>';
        echo '<option value="ready">✅ Ready</option>';
        echo '<option value="draft">⏳ Draft</option>';
        echo '<option value="inactive">❌ Inactive</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="table-responsive">';
        echo '<table class="table table-hover mb-0" id="templatesTable">';
        echo '<thead class="thead-light">';
        echo '<tr>';
        echo '<th style="width: 40%;">Unit Name</th>';
        echo '<th style="width: 15%;">Zoho ID</th>';
        echo '<th style="width: 10%;">Definition</th>';
        echo '<th style="width: 10%;">Criteria</th>';
        echo '<th style="width: 10%;">Status</th>';
        echo '<th style="width: 15%;">Last Synced</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($templates as $template) {
            echo '<tr>';
            
            // Unit Name
            echo '<td>';
            echo '<strong>' . htmlspecialchars($template->unit_name) . '</strong>';
            if ($template->areaid) {
                echo '<br><small class="text-muted">Area ID: ' . $template->areaid . '</small>';
            }
            echo '</td>';
            
            // Zoho Unit ID
            echo '<td><code class="small">' . htmlspecialchars(substr($template->zoho_unit_id, -12)) . '</code></td>';
            
            // Definition ID with link (fixed with areaid parameter)
            echo '<td>';
            if ($template->definition_id && $template->areaid) {
                echo '<a href="' . new moodle_url('/grade/grading/form/btec/edit.php', [
                    'areaid' => $template->areaid,
                    'id' => $template->definition_id
                ]) . '" target="_blank" title="Edit grading form">';
                echo '<span class="badge badge-info">#' . $template->definition_id . '</span>';
                echo '</a>';
            } elseif ($template->definition_id) {
                echo '<span class="badge badge-secondary" title="Missing area ID">#' . $template->definition_id . '</span>';
            } else {
                echo '<span class="badge badge-secondary">None</span>';
            }
            echo '</td>';
            
            // Criteria Count
            echo '<td>';
            if ($template->criteria_count > 0) {
                echo '<span class="badge badge-success">' . $template->criteria_count . ' criteria</span>';
            } else {
                echo '<span class="badge badge-warning">0 criteria</span>';
            }
            echo '</td>';
            
            // Status badge with data attribute for filtering
            echo '<td>';
            if ($template->definition_status == 20) {
                echo '<span class="badge badge-success" data-status="ready"><i class="fa fa-check"></i> Ready</span>';
            } elseif ($template->definition_status > 0) {
                echo '<span class="badge badge-warning" data-status="draft"><i class="fa fa-clock"></i> Draft</span>';
            } else {
                echo '<span class="badge badge-secondary" data-status="inactive"><i class="fa fa-times"></i> Inactive</span>';
            }
            echo '</td>';
            
            // Synced At
            echo '<td>';
            echo '<small>' . userdate($template->synced_at, '%d %b %Y<br>%H:%M:%S') . '</small>';
            echo '</td>';
            
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>'; // table-responsive
        echo '</div>'; // card-body
        echo '</div>'; // card
        
        // Add search and filter functionality (only if table exists)
        echo '<script>
        // Filter function for table (only works if table exists)
        if (document.getElementById("templateSearch")) {
            function filterTable() {
                var searchText = document.getElementById("templateSearch").value.toLowerCase();
                var statusFilter = document.getElementById("statusFilter").value;
                var rows = document.querySelectorAll("#templatesTable tbody tr");
                
                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    var statusBadge = row.querySelector("[data-status]");
                    var status = statusBadge ? statusBadge.getAttribute("data-status") : "";
                    
                    var matchesSearch = text.includes(searchText);
                    var matchesStatus = statusFilter === "all" || status === statusFilter;
                    
                    row.style.display = (matchesSearch && matchesStatus) ? "" : "none";
                });
            }
            
            document.getElementById("templateSearch").addEventListener("keyup", filterTable);
            document.getElementById("statusFilter").addEventListener("change", filterTable);
        }
        </script>';
                
    } else {
        echo '<div class="alert alert-warning">';
        echo '<h5><i class="fa fa-exclamation-triangle"></i> No Templates Found</h5>';
        echo '<p>No templates have been synced yet. Click "Sync All Templates" above to fetch templates from Zoho.</p>';
        echo '</div>';
    }
} else {
    echo '<div class="alert alert-danger">';
    echo '<h5><i class="fa fa-exclamation-circle"></i> Database Table Missing</h5>';
    echo '<p>The templates tracking table has not been created yet. Please run the database upgrade:</p>';
    echo '<p><a href="' . new moodle_url('/admin/index.php') . '" class="btn btn-primary">Go to Site Administration</a></p>';
    echo '</div>';
}

// Sync button handler (ALWAYS present, regardless of data)
echo '<script>
// Sync button AJAX handler
if (document.getElementById("syncButton")) {
    document.getElementById("syncButton").addEventListener("click", function() {
        var btn = this;
        var progressDiv = document.getElementById("syncProgress");
        var progressBar = document.getElementById("progressBar");
        var syncStatus = document.getElementById("syncStatus");
        var syncResult = document.getElementById("syncResult");
        
        // Disable button
        btn.disabled = true;
        btn.innerHTML = "<i class=\\"fa fa-spinner fa-spin\\"></i> Syncing...";
        
        // Show progress
        progressDiv.style.display = "block";
        syncResult.style.display = "none";
        
        // Simulate progress (since we dont know exact count upfront)
        var progress = 0;
        var progressInterval = setInterval(function() {
            if (progress < 90) {
                progress += Math.random() * 10;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + "%";
                progressBar.textContent = Math.round(progress) + "%";
            }
        }, 500);
        
        syncStatus.textContent = "Fetching templates from Zoho...";
        
        // Make AJAX request
        fetch("' . $backend_url . '/api/v1/btec/sync-templates", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            }
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            
            // Complete progress
            progressBar.style.width = "100%";
            progressBar.textContent = "100%";
            progressBar.classList.remove("progress-bar-animated");
            progressBar.classList.add("bg-success");
            
            syncStatus.textContent = "Sync completed!";
            
            // Show results
            var successCount = data.success || 0;
            var failedCount = data.failed || 0;
            var totalCount = data.total || 0;
            
            syncResult.className = "alert alert-success mt-3";
            syncResult.innerHTML = 
                "<h5><i class=\\"fa fa-check-circle\\"></i> Sync Complete!</h5>" +
                "<ul class=\\"mb-0\\">" +
                "<li><strong>Total:</strong> " + totalCount + " templates</li>" +
                "<li><strong>✅ Success:</strong> " + successCount + " templates</li>" +
                "<li><strong>❌ Failed:</strong> " + failedCount + " templates</li>" +
                "</ul>" +
                "<p class=\\"mt-2 mb-0\\"><small>Refreshing page in 3 seconds...</small></p>";
            syncResult.style.display = "block";
            
            // Refresh page after 3 seconds
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        })
        .catch(error => {
            clearInterval(progressInterval);
            
            progressBar.classList.remove("progress-bar-animated");
            progressBar.classList.add("bg-danger");
            syncStatus.textContent = "Sync failed!";
            
            syncResult.className = "alert alert-danger mt-3";
            syncResult.innerHTML = 
                "<h5><i class=\\"fa fa-exclamation-circle\\"></i> Sync Failed</h5>" +
                "<p>" + error.message + "</p>";
            syncResult.style.display = "block";
            
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = "<i class=\\"fa fa-sync\\"></i> Sync All Templates";
        });
    });
}
</script>';

// Instructions
echo '<div class="card mt-4">';
echo '<div class="card-header bg-light">';
echo '<h5 class="mb-0"><i class="fa fa-question-circle"></i> How to Use BTEC Templates</h5>';
echo '</div>';
echo '<div class="card-body">';
echo '<ol>';
echo '<li><strong>Sync Templates:</strong> Click "Sync All Templates" to fetch from Zoho</li>';
echo '<li><strong>Review Synced Data:</strong> Check the table below to see all synced templates</li>';
echo '<li><strong>Verify Criteria:</strong> Each template should show criteria count (P, M, D levels)</li>';
echo '<li><strong>Edit if Needed:</strong> Click definition ID to edit criteria in Moodle</li>';
echo '<li><strong>Use in Assignments:</strong> Go to assignment settings → Advanced grading → Choose BTEC method → Select template</li>';
echo '</ol>';
echo '<div class="alert alert-info mt-3 mb-0">';
echo '<strong>Note:</strong> Grading areas are created automatically for each unit. Templates are updated on re-sync without creating duplicates.';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // Close mzi-content-wrapper
echo '</div>'; // Close mzi-page-container

echo $OUTPUT->footer();
