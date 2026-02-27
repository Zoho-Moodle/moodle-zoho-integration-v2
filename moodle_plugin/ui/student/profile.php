<?php
/**
 * Student Profile Page — Tab 1 of 4
 *
 * Displays student profile fields. All changes are submitted as Student_Requests
 * (Change Information / Student Card) that flow to Zoho via middleware.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/profile.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My Profile');
$PAGE->set_heading('My Profile');

$userid  = $USER->id;
$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$userid]
);
$photo_url = new moodle_url('/local/moodle_zoho_sync/ui/student/serve_photo.php', ['uid' => $userid]);

echo $OUTPUT->header();
?>
<style>
.sd-wrap{max-width:1610px;margin:0 auto;padding:0 12px 40px}
.sd-nav{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:24px;padding:10px 0;border-bottom:2px solid #e0e0e0}
.sd-nav a{padding:8px 11px;border-radius:6px 6px 0 0;text-decoration:none;color:#555;font-weight:500;font-size:13px;transition:all .2s;border:1px solid transparent;border-bottom:none;background:#f8f9fa}
.sd-nav a:hover{background:#e9ecef;color:#333}
.sd-nav a.active{background:#fff;color:#0066cc;border-color:#0066cc;font-weight:700}
.sd-nav a i{margin-right:5px}
/* Profile shell */
.sd-profile-shell{display:flex;gap:32px;background:#fff;border-radius:14px;padding:28px 32px;box-shadow:0 2px 14px rgba(0,0,0,.08);flex-wrap:wrap}
.sd-profile-photo-col{display:flex;flex-direction:column;align-items:center;gap:10px;min-width:130px}
.sd-profile-avatar{width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #0066cc}
.sd-profile-avatar-placeholder{width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#0066cc,#004a99);display:flex;align-items:center;justify-content:center;font-size:48px;color:#fff}
.sd-status-badge{padding:4px 16px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-top:4px}
.sd-status-badge.active{background:#d4edda;color:#155724}
.sd-status-badge.inactive{background:#f8d7da;color:#721c24}
.sd-sid-box{font-size:13px;color:#555;background:#f0f4ff;padding:4px 12px;border-radius:8px;font-family:monospace}
/* Fields */
.sd-profile-fields-col{flex:1;min-width:280px}
.sd-profile-name{margin:0 0 18px;font-size:22px;font-weight:700;color:#1a1a2e}
.sd-fields-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px 24px;margin-bottom:20px}
.sd-field-lbl{font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px}
.sd-field-val{font-size:14px;color:#222}
.sd-na{color:#bbb}
.sd-reveal-btn{background:none;border:1px solid #0066cc;color:#0066cc;border-radius:4px;padding:1px 8px;font-size:11px;cursor:pointer;margin-left:6px}
.sd-reveal-btn:hover{background:#0066cc;color:#fff}
/* Buttons */
.sd-profile-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px}
.sd-btn{padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all .2s;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
.sd-btn-primary{background:#0066cc;color:#fff}.sd-btn-primary:hover{background:#004fa3}
.sd-btn-secondary{background:#6f42c1;color:#fff}.sd-btn-secondary:hover{background:#5a32a3}
.sd-btn-ghost{background:#f1f3f5;color:#444;border:1px solid #ddd}.sd-btn-ghost:hover{background:#e9ecef}
.sd-btn:disabled{opacity:.5;cursor:not-allowed}
/* Modal */
.sd-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center}
.sd-modal{background:#fff;border-radius:12px;width:min(560px,95vw);box-shadow:0 8px 32px rgba(0,0,0,.25);overflow:hidden}
.sd-modal-head{padding:16px 20px;background:#0066cc;color:#fff;display:flex;justify-content:space-between;align-items:center;font-size:15px}
.sd-modal-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1}
.sd-modal-body{padding:20px}
.sd-modal-foot{padding:14px 20px;background:#f8f9fa;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #e0e0e0}
.sd-form-row{margin-bottom:14px}
.sd-form-row label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;text-transform:uppercase;letter-spacing:.3px}
.sd-form-ctrl{width:100%;padding:8px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box}
.sd-form-ctrl:focus{border-color:#0066cc;outline:none}
</style>
<div class="sd-wrap">
    <nav class="sd-nav">
        <a href="profile.php" class="active"><i class="fa fa-user"></i> Profile</a>
        <a href="programs.php"><i class="fa fa-graduation-cap"></i> My Programs</a>
        <a href="classes.php"><i class="fa fa-calendar"></i> My Classes &amp; Grades</a>
        <a href="requests.php"><i class="fa fa-file-text"></i> My Requests</a>
        <a href="student_card.php"><i class="fa fa-id-card"></i> Student Card</a>
    </nav>

<?php if (!$student): ?>
    <div class="alert alert-warning"><strong>No student record found.</strong> Please contact the administration office.</div>
<?php else: ?>
    <?php
    $is_active = strtolower($student->status ?? '') === 'active';
    $st_cls    = $is_active ? 'active' : 'inactive';
    ?>
    <div class="sd-profile-shell">
        <!-- Photo column -->
        <div class="sd-profile-photo-col">
            <?php if (!empty($student->photo_url)): ?>
                <img src="<?php echo $photo_url; ?>" alt="Photo" class="sd-profile-avatar">
            <?php else: ?>
                <div class="sd-profile-avatar-placeholder"><i class="fa fa-user"></i></div>
            <?php endif; ?>
            <span class="sd-status-badge <?php echo $st_cls; ?>"><?php echo s($student->status ?: 'Unknown'); ?></span>
            <?php if (!empty($student->student_id)): ?>
                <div class="sd-sid-box">ID: <?php echo s($student->student_id); ?></div>
            <?php endif; ?>
        </div>

        <!-- Fields column -->
        <div class="sd-profile-fields-col">
            <h2 class="sd-profile-name"><?php echo s($student->first_name . ' ' . $student->last_name); ?></h2>
            <div class="sd-fields-grid">
                <?php
                function sd_field(string $label, ?string $val, bool $mono = false): void {
                    $v = $val !== null && $val !== '' ? s($val) : '<span class="sd-na">—</span>';
                    $cls = $mono ? ' style="font-family:monospace"' : '';
                    echo "<div><div class=\"sd-field-lbl\">{$label}</div><div class=\"sd-field-val\"{$cls}>{$v}</div></div>";
                }
                // Format a raw date value (Zoho may send YYYY-MM-DD string or Unix ms timestamp)
                function sd_format_date(?string $raw): ?string {
                    if ($raw === null || $raw === '') return null;
                    if (is_numeric($raw)) {
                        // Unix timestamp: ms (13 digits) or seconds (10 digits)
                        $ts = strlen($raw) >= 13 ? intval($raw) / 1000 : intval($raw);
                        return date('d M Y', (int)$ts);
                    }
                    // Try parsing date strings like YYYY-MM-DD, DD-MMM-YYYY, etc.
                    $ts = strtotime($raw);
                    return $ts ? date('d M Y', $ts) : $raw;
                }
                sd_field('First Name',      $student->first_name);
                sd_field('Last Name',       $student->last_name);
                sd_field('Academic Email',  $student->academic_email ?: $student->email);
                sd_field('Phone Number',    $student->phone_number);
                sd_field('Date of Birth',   sd_format_date($student->date_of_birth ?? null));
                sd_field('Nationality',     $student->nationality);
                sd_field('Address',         $student->address ?? null);
                sd_field('City',            $student->city ?? null);
                $last_upd = !empty($student->updated_at) && $student->updated_at > 0
                    ? userdate($student->updated_at, '%d %b %Y %H:%M') : '—';
                sd_field('Last Synced', $last_upd);
                ?>
                <!-- National ID — hidden by default -->
                <div>
                    <div class="sd-field-lbl">National ID</div>
                    <div class="sd-field-val">
                        <?php if (!empty($student->national_id ?? null)): // column may not exist yet — safe check ?>
                            <span id="nid-mask">••••••••••</span>
                            <span id="nid-val" style="display:none;font-family:monospace"><?php echo s($student->national_id); ?></span>
                            <button type="button" onclick="toggleNID(this)" class="sd-reveal-btn">Reveal</button>
                        <?php else: ?>
                            <span class="sd-na">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sd-profile-actions">
                <button type="button" onclick="document.getElementById('updateInfoModal').style.display='flex'" class="sd-btn sd-btn-primary">
                    <i class="fa fa-pencil"></i> Update My Information
                </button>
            </div>
            <p style="font-size:12px;color:#888">
                <i class="fa fa-info-circle"></i>
                All changes require an admin review — no direct edits are permitted.
            </p>
        </div>
    </div><!-- /.sd-profile-shell -->

    <!-- Update Info Modal -->
    <div id="updateInfoModal" class="sd-modal-overlay" style="display:none">
        <div class="sd-modal">
            <div class="sd-modal-head">
                <strong>Request Information Update</strong>
                <button type="button" onclick="this.closest('.sd-modal-overlay').style.display='none'" class="sd-modal-close">×</button>
            </div>
            <div class="sd-modal-body">
                <p style="font-size:13px;color:#555;margin-bottom:16px">Describe what you would like to update. The administration will review your request.</p>
                <div class="sd-form-row">
                    <label>Field to update</label>
                    <select id="uiField" class="sd-form-ctrl">
                        <option value="">— select field —</option>
                        <option>Phone Number</option><option>Address</option>
                        <option>Email</option><option>Date of Birth</option>
                        <option>Nationality</option><option>Name (correction)</option><option>Other</option>
                    </select>
                </div>
                <div class="sd-form-row">
                    <label>Current value (as you know it)</label>
                    <input type="text" id="uiOldVal" class="sd-form-ctrl" placeholder="Optional">
                </div>
                <div class="sd-form-row">
                    <label>Requested new value <span style="color:red">*</span></label>
                    <input type="text" id="uiNewVal" class="sd-form-ctrl" placeholder="Required">
                </div>
                <div class="sd-form-row">
                    <label>Additional notes</label>
                    <textarea id="uiNotes" class="sd-form-ctrl" rows="3" placeholder="Any supporting details…"></textarea>
                </div>
                <div id="uiResult" style="display:none;margin-top:12px;font-size:13px;padding:10px;border-radius:6px"></div>
            </div>
            <div class="sd-modal-foot">
                <button type="button" id="uiSubmitBtn" onclick="submitUpdateInfo()" class="sd-btn sd-btn-primary">
                    <i class="fa fa-paper-plane"></i> Submit Request
                </button>
                <button type="button" onclick="this.closest('.sd-modal-overlay').style.display='none'" class="sd-btn sd-btn-ghost">Cancel</button>
            </div>
        </div>
    </div>
<?php endif; ?>
</div><!-- /.sd-wrap -->

<script>
var SUBMIT_URL = '<?php echo (new moodle_url('/local/moodle_zoho_sync/ui/ajax/submit_request.php'))->out(false); ?>';
var SESSKEY    = '<?php echo sesskey(); ?>';

function toggleNID(btn) {
    var mask=document.getElementById('nid-mask'), val=document.getElementById('nid-val');
    if(val.style.display==='none'){mask.style.display='none';val.style.display='inline';btn.textContent='Hide';}
    else{mask.style.display='inline';val.style.display='none';btn.textContent='Reveal';}
}

async function submitUpdateInfo() {
    var field=document.getElementById('uiField').value.trim(),
        oldVal=document.getElementById('uiOldVal').value.trim(),
        newVal=document.getElementById('uiNewVal').value.trim(),
        notes=document.getElementById('uiNotes').value.trim(),
        res=document.getElementById('uiResult'),
        btn=document.getElementById('uiSubmitBtn');
    if(!field||!newVal){showResult(res,false,'Please select a field and enter the requested new value.');return;}
    btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Submitting…';
    var desc='Field: '+field+(oldVal?'\nCurrent: '+oldVal:'')+'\nRequested: '+newVal+(notes?'\nNotes: '+notes:'');
    doSubmitRequest({request_type:'Change Information', description:desc, reason:'Update '+field}, function(ok,msg) {
        showResult(res,ok,msg); btn.disabled=false; btn.innerHTML='<i class="fa fa-paper-plane"></i> Submit Request';
        if(ok){['uiField','uiOldVal','uiNewVal','uiNotes'].forEach(function(id){document.getElementById(id).value='';}); btn.disabled=true;}
    });
}

async function doSubmitRequest(data, cb) {
    try {
        data.sesskey=SESSKEY;
        var r=await(await fetch(SUBMIT_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})).json();
        cb(r.success===true, r.message||r.error||'Unknown response');
    } catch(e){cb(false,'Network error: '+e.message);}
}

function showResult(el,ok,msg){
    el.style.display='block';el.style.background=ok?'#d4edda':'#f8d7da';el.style.color=ok?'#155724':'#721c24';
    el.textContent=(ok?'✅ ':'❌ ')+msg;
}
</script>
<?php echo $OUTPUT->footer(); ?>
