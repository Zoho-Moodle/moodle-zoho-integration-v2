<?php
/**
 * Student Requests Page - Tab 5 of 6
 *
 * Displays submit-request form (with request-window enforcement) and
 * a list of the student's existing requests.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/requests.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Requests');
$PAGE->set_heading('Requests');

$userid = $USER->id;

$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$userid]
);

$now = time();
$all_types = [
    'Enroll Next Semester',
    'Class Drop',
    'Late Submission',
    'Change Information',
    'Student Card',
];

$type_windows = [];
$window_rows = $DB->get_records('local_mzi_request_windows');
foreach ($window_rows as $wr) {
    $type_windows[$wr->request_type] = $wr;
}

$available_types = [];
$restricted_msg  = [];

foreach ($all_types as $type) {
    $wr      = $type_windows[$type] ?? null;
    $enabled = $wr ? (bool)(int)$wr->enabled : true;
    if (!$enabled) {
        $restricted_msg[$type] = $wr->message ?: 'This request type is currently unavailable.';
        continue;
    }
    $after_start  = (!$wr || !$wr->start_date || $now >= (int)$wr->start_date);
    $before_end   = (!$wr || !$wr->end_date   || $now <= (int)$wr->end_date);
    if (!$after_start) {
        $restricted_msg[$type] = $wr->message ?: 'Submissions not yet open.';
        continue;
    }
    if (!$before_end) {
        $restricted_msg[$type] = $wr->message ?: 'The submission window for this type has closed.';
        continue;
    }
    $available_types[] = $type;
}

$requests = [];
if ($student) {
    $requests = $DB->get_records_sql(
        "SELECT * FROM {local_mzi_requests} WHERE student_id = ? ORDER BY created_at DESC",
        [$student->id]
    );
}

$submit_url = (new moodle_url('/local/moodle_zoho_sync/ui/ajax/submit_request.php'))->out(false);
$sesskey_val = sesskey();

echo $OUTPUT->header();
?>
<style>
.sd-wrap{max-width:960px;margin:0 auto;padding:0 12px 40px}
.sd-nav{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:24px;padding:10px 0;border-bottom:2px solid #e0e0e0}
.sd-nav a{padding:8px 16px;border-radius:6px 6px 0 0;text-decoration:none;color:#555;font-weight:500;font-size:14px;transition:all .2s;border:1px solid transparent;border-bottom:none;background:#f8f9fa}
.sd-nav a:hover{background:#e9ecef;color:#333}
.sd-nav a.active{background:#fff;color:#0066cc;border-color:#0066cc;font-weight:700}
.sd-nav a i{margin-right:5px}
.sd-form-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px;margin-bottom:28px}
.sd-form-card h4{margin:0 0 16px;font-size:16px;color:#1a1a2e;font-weight:700}
.sd-form-row{margin-bottom:16px}
.sd-form-row label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
.sd-form-row select,.sd-form-row textarea{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:8px;font-size:14px;font-family:inherit;transition:border-color .2s;box-sizing:border-box}
.sd-form-row select:focus,.sd-form-row textarea:focus{border-color:#0066cc;outline:none;box-shadow:0 0 0 3px rgba(0,102,204,.1)}
.sd-form-row textarea{min-height:100px;resize:vertical}
.sd-submit-btn{padding:10px 26px;background:#0066cc;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer}
.sd-submit-btn:hover{background:#004fa3}
.sd-submit-btn:disabled{background:#90b8e0;cursor:not-allowed}
.sd-type-hint{margin-top:6px;padding:8px 12px;border-radius:6px;font-size:12px}
.sd-type-hint.closed{background:#fff3cd;color:#856404}
.sd-type-hint.open{background:#d4edda;color:#155724}
.sd-flash{padding:12px 18px;border-radius:8px;margin-bottom:16px;font-size:14px}
.sd-flash.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.sd-flash.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
.sd-requests-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px}
.sd-requests-card h4{margin:0 0 16px;font-size:16px;color:#1a1a2e;font-weight:700}
.sd-rtbl{width:100%;border-collapse:collapse;font-size:13px}
.sd-rtbl th{background:#f8f9fa;color:#555;font-weight:600;text-align:left;padding:9px 12px;border-bottom:2px solid #e0e0e0;font-size:11px;text-transform:uppercase;letter-spacing:.4px}
.sd-rtbl td{padding:10px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.sd-rtbl tr:last-child td{border-bottom:none}
.sd-rtbl tr:hover td{background:#f9fbff}
.sd-status{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;text-transform:uppercase}
.sd-status.submitted{background:#cce5ff;color:#004085}
.sd-status.under-review{background:#fff3cd;color:#856404}
.sd-status.approved{background:#d4edda;color:#155724}
.sd-status.rejected{background:#f8d7da;color:#721c24}
.sd-status.other{background:#e9ecef;color:#495057}
</style>

<div class="sd-wrap">
    <nav class="sd-nav">
        <a href="profile.php"><i class="fa fa-user"></i> Profile</a>
        <a href="programs.php"><i class="fa fa-graduation-cap"></i> My Programs</a>
        <a href="classes.php"><i class="fa fa-calendar"></i> My Classes</a>
        <a href="grades.php"><i class="fa fa-star"></i> My Grades</a>
        <a href="requests.php" class="active"><i class="fa fa-file-text"></i> My Requests</a>
        <a href="student_card.php"><i class="fa fa-id-card"></i> Student Card</a>
    </nav>

    <?php if (!$student): ?>
        <div class="alert alert-warning"><strong>No student record found.</strong> Please contact the administration office.</div>
    <?php else: ?>

    <div class="sd-form-card">
        <h4><i class="fa fa-plus-circle" style="color:#0066cc;margin-right:6px"></i>Submit a New Request</h4>
        <div id="sd_flash" class="sd-flash" style="display:none"></div>

        <?php if (empty($available_types)): ?>
            <p style="color:#856404;background:#fff3cd;padding:12px;border-radius:8px;font-size:14px">
                <i class="fa fa-clock-o"></i> There are no request types currently available. Please check back later or contact the administrative office.
            </p>
        <?php else: ?>
        <form id="sd_req_form" onsubmit="return false">
            <div class="sd-form-row">
                <label for="sd_req_type">Request Type <span style="color:#c0392b">*</span></label>
                <select id="sd_req_type" name="request_type" onchange="updateTypeHint()">
                    <option value="">Select a request type</option>
                    <?php foreach ($all_types as $type): ?>
                        <?php $open = in_array($type, $available_types); ?>
                        <option value="<?php echo s($type); ?>"<?php if (!$open) echo ' disabled style="color:#aaa"'; ?>>
                            <?php echo s($type); ?><?php if (!$open) echo ' (unavailable)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="sd_type_hint" class="sd-type-hint" style="display:none"></div>
            </div>
            <div class="sd-form-row">
                <label for="sd_req_reason">Brief Reason</label>
                <select id="sd_req_reason" name="reason">
                    <option value="">Optional</option>
                    <option>Personal Circumstances</option>
                    <option>Academic Reasons</option>
                    <option>Financial Reasons</option>
                    <option>Medical / Health</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="sd-form-row">
                <label for="sd_req_desc">Description <span style="color:#c0392b">*</span></label>
                <textarea id="sd_req_desc" name="description" placeholder="Please provide details about your request (minimum 20 characters)"></textarea>
            </div>
            <button type="button" class="sd-submit-btn" id="sd_submit_btn" onclick="submitRequest()">
                <i class="fa fa-paper-plane"></i> Submit Request
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="sd-requests-card">
        <h4><i class="fa fa-list-alt" style="color:#0066cc;margin-right:6px"></i>My Requests</h4>
        <?php if (empty($requests)): ?>
            <p style="color:#888;text-align:center;padding:24px 0;font-size:13px">
                <i class="fa fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;color:#ccc"></i>
                You have not submitted any requests yet.
            </p>
        <?php else: ?>
            <table class="sd-rtbl">
                <thead><tr>
                    <th>#</th><th>Type</th><th>Status</th>
                    <th>Submitted</th><th>Admin Response</th>
                </tr></thead>
                <tbody>
                <?php foreach ($requests as $req):
                    $st_raw = strtolower(str_replace(' ', '-', $req->request_status ?? ''));
                    $st_cls = in_array($st_raw, ['submitted','under-review','approved','rejected']) ? $st_raw : 'other';
                    $date_str = !empty($req->created_at) ? userdate($req->created_at, '%d %b %Y') : 'N/A';
                ?>
                    <tr>
                        <td style="color:#888;font-size:12px"><?php echo s($req->request_number ?: $req->id); ?></td>
                        <td><?php echo s($req->request_type); ?></td>
                        <td><span class="sd-status <?php echo $st_cls; ?>"><?php echo s($req->request_status ?? 'Unknown'); ?></span></td>
                        <td><?php echo $date_str; ?></td>
                        <td style="font-size:12px;color:#555;max-width:260px">
                            <?php echo !empty($req->admin_response) ? s($req->admin_response) : '<span style="color:#aaa">N/A</span>'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<script>
var SUBMIT_URL = '<?php echo $submit_url; ?>';
var SESSKEY    = '<?php echo $sesskey_val; ?>';
var restrictedMsg = <?php echo json_encode($restricted_msg); ?>;

function showFlash(msg, type) {
    var el = document.getElementById('sd_flash');
    el.className = 'sd-flash ' + type;
    el.textContent = msg;
    el.style.display = 'block';
}
function updateTypeHint() {
    var sel  = document.getElementById('sd_req_type').value;
    var hint = document.getElementById('sd_type_hint');
    if (!sel) { hint.style.display = 'none'; return; }
    if (restrictedMsg[sel]) {
        hint.className = 'sd-type-hint closed';
        hint.textContent = 'Warning: ' + restrictedMsg[sel];
        hint.style.display = 'block';
    } else {
        hint.className = 'sd-type-hint open';
        hint.textContent = 'Submissions for this type are currently open.';
        hint.style.display = 'block';
    }
}
async function submitRequest() {
    var type = document.getElementById('sd_req_type').value;
    var desc = document.getElementById('sd_req_desc').value.trim();
    var reason = document.getElementById('sd_req_reason').value;
    var btn  = document.getElementById('sd_submit_btn');
    if (!type) { showFlash('Please select a request type.', 'error'); return; }
    if (desc.length < 20) { showFlash('Description must be at least 20 characters.', 'error'); return; }
    btn.disabled = true; btn.textContent = 'Submitting...';
    try {
        var r = await (await fetch(SUBMIT_URL, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({sesskey: SESSKEY, request_type: type, description: desc, reason: reason})
        })).json();
        if (r.success) {
            showFlash('Request submitted successfully. Reference: ' + (r.request_number || 'N/A'), 'success');
            document.getElementById('sd_req_form').reset();
            document.getElementById('sd_type_hint').style.display = 'none';
            setTimeout(function(){ location.reload(); }, 2000);
        } else {
            showFlash('Error: ' + (r.error || 'Could not submit request.'), 'error');
        }
    } catch(e) {
        showFlash('Network error: ' + e.message, 'error');
    }
    btn.disabled = false; btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Request';
}
</script>
<?php echo $OUTPUT->footer(); ?>