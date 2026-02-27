<?php
/**
 * Student Card – Selection & Preview
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_login();

$reg_id = optional_param('reg_id', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/student_card.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Student Card');
$PAGE->set_heading('Student Card');

$userid = $USER->id;

// ── Student record ──────────────────────────────────────────────────────────
$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$userid]
);

// ── Active registrations ────────────────────────────────────────────────────
$registrations = [];
if ($student) {
    $registrations = $DB->get_records_sql(
        "SELECT * FROM {local_mzi_registrations}
         WHERE student_id = ?
         ORDER BY registration_date DESC",
        [$student->id]
    );
}

// ── Pick registration ───────────────────────────────────────────────────────
$reg = null;
if ($registrations) {
    if ($reg_id && isset($registrations[$reg_id])) {
        $reg = $registrations[$reg_id];
    } else {
        $reg = reset($registrations);
    }
}

// ── Derived fields ──────────────────────────────────────────────────────────
$valid_until      = '';
$academic_program = '';
$dob_formatted    = '';

if ($reg) {
    $rd = strtotime($reg->registration_date);
    $valid_until      = $rd ? date('M j, Y', strtotime('+2 years', $rd)) : '';
    $academic_program = trim(($reg->program_name ?? '') . ' ' . ($reg->program_level ?? ''));
}

if ($student && !empty($student->date_of_birth)) {
    $ts = strtotime($student->date_of_birth);
    if ($ts) {
        $dob_formatted = date('M j, Y', $ts);
    }
}

$logo_abc      = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/ABC%20Horizon%20Logo%20Full%20Color.png';
$logo_pearson  = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/Pearson.png';
$logo_btec     = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/BTEC.png';
$photo_url     = (new moodle_url('/local/moodle_zoho_sync/ui/student/serve_photo.php', ['uid' => $userid]))->out(false);

$student_id_val = $student ? ($student->student_id ?: ($student->zoho_student_id ?: '')) : '';
$photo_pending_status = $student ? ($student->photo_pending_status ?? '') : '';

echo $OUTPUT->header();
?>

<style>
/* ── nav ─────────────────────────────────────────────────── */
.sd-wrap{max-width:1380px;margin:0 auto;padding:0 10px}
.sd-nav{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:24px;padding:10px 0;border-bottom:2px solid #e0e0e0}
.sd-nav a{padding:8px 11px;border-radius:6px 6px 0 0;text-decoration:none;color:#555;font-weight:500;font-size:13px;transition:all .2s;border:1px solid transparent;border-bottom:none;background:#f8f9fa}
.sd-nav a:hover{background:#e9ecef;color:#333}
.sd-nav a.active{background:#fff;color:#0066cc;border-color:#0066cc;font-weight:700}
.sd-nav a i{margin-right:5px}

/* ── card shell ──────────────────────────────────────────── */
.sc-card{width:620px;border-radius:8px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.18);background:#fff;position:relative;font-family:'Segoe UI',Arial,sans-serif;border-top:3px solid #111;border-bottom:3px solid #111;display:flex;flex-direction:column}
.sc-top-bar{height:11px;background:linear-gradient(90deg,#F15A24 0%,#F15A24 33.33%,#1565C0 33.33%,#1565C0 66.66%,#1E6B2D 66.66%,#1E6B2D 100%);flex-shrink:0}
.sc-inner{padding:16px 20px 12px;flex:1}

/* ── header row ──────────────────────────────────────────── */
.sc-header-row{position:relative;margin-bottom:2px;min-height:28px}
.sc-title{font-size:15px;font-weight:800;letter-spacing:.08em;color:#1a1a1a;text-transform:uppercase;padding-top:4px;display:block}
.sc-abc-logo{position:absolute;top:0;right:0;height:86px;object-fit:contain}

/* ── body ────────────────────────────────────────────────── */
.sc-body-row{display:flex;gap:20px;align-items:flex-start}
.sc-photo-col{display:flex;flex-direction:column;align-items:center;min-width:108px}
.sc-photo-frame{width:108px;height:135px;border:2px solid #ddd;border-radius:4px;overflow:hidden;background:#f0f0f0;display:flex;align-items:center;justify-content:center}
.sc-photo-frame img{width:100%;height:100%;object-fit:cover}
.sc-photo-placeholder{color:#aaa;font-size:42px}
.sc-id-label{margin-top:6px;font-size:10px;font-weight:700;color:#444;letter-spacing:.06em;text-align:center}

/* ── info fields ─────────────────────────────────────────── */
.sc-info-col{flex:1}
.sc-field{display:flex;margin-bottom:7px;align-items:baseline}
.sc-field-label{font-size:10px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.06em;min-width:140px}
.sc-field-value{font-size:12px;font-weight:700;color:#1a1a1a;flex:1}

/* ── bottom bar ──────────────────────────────────────────── */
.sc-logos-row{background:#f0f0f0;display:flex;align-items:center;justify-content:space-between;padding:8px 16px;flex-shrink:0}
.sc-bottom-logos{display:flex;align-items:center;gap:14px}
.sc-pearson-logo,.sc-btec-logo{height:26px;object-fit:contain}
.sc-barcode-wrap svg{height:28px;display:block}
.sc-bottom-bar{height:11px;background:linear-gradient(90deg,#F15A24 0%,#F15A24 33.33%,#1565C0 33.33%,#1565C0 66.66%,#1E6B2D 66.66%,#1E6B2D 100%);flex-shrink:0}

/* ── preview wrapper ─────────────────────────────────────── */
.sc-preview-wrap{display:flex;justify-content:center;margin-bottom:20px}
.sc-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:24px}

/* ── photo upload ────────────────────────────────────────── */
.sc-upload-area{display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.sc-upload-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;background:#1565C0;color:#fff;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s;border:none}
.sc-upload-btn:hover{background:#0d47a1}
.sc-upload-status{font-size:12px;color:#555}
.sc-upload-status.ok{color:#1E6B2D;font-weight:600}
.sc-upload-status.err{color:#c62828;font-weight:600}
.sc-photo-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600}
.sc-photo-badge.pending{background:#fff8e1;color:#e65100;border:1px solid #ffcc02}
.sc-photo-badge.rejected{background:#ffebee;color:#c62828;border:1px solid #ef9a9a}
.sc-photo-badge.approved{background:#e8f5e9;color:#1E6B2D;border:1px solid #a5d6a7}

/* ── crop modal ──────────────────────────────────────────── */
.sc-crop-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:9999;align-items:center;justify-content:center}
.sc-crop-backdrop.open{display:flex}
.sc-crop-box{background:#fff;border-radius:10px;width:min(520px,96vw);max-height:96vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.4)}
.sc-crop-header{padding:14px 18px 10px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between}
.sc-crop-header h3{margin:0;font-size:15px;font-weight:700;color:#1a1a1a}
.sc-crop-close{background:none;border:none;font-size:20px;cursor:pointer;color:#666;line-height:1;padding:0 4px}
.sc-crop-close:hover{color:#c62828}
.sc-crop-preview{flex:1;overflow:hidden;background:#1a1a1a;max-height:380px}
.sc-crop-preview img{display:block;max-width:100%;max-height:380px}
.sc-crop-controls{padding:10px 16px;border-top:1px solid #e0e0e0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sc-crop-controls button{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1px solid #ccc;border-radius:5px;background:#f5f5f5;font-size:12px;font-weight:600;cursor:pointer;color:#333}
.sc-crop-controls button:hover{background:#e0e0e0}
.sc-crop-confirm{background:#1565C0 !important;color:#fff !important;border-color:#1565C0 !important;margin-left:auto}
.sc-crop-confirm:hover{background:#0d47a1 !important}
.sc-crop-hint{font-size:11px;color:#888;width:100%;text-align:center;padding:0 0 4px}
</style>

<div class="sd-wrap">
  <nav class="sd-nav">
    <a href="profile.php"><i class="fa fa-user"></i> Profile</a>
    <a href="programs.php"><i class="fa fa-graduation-cap"></i> My Programs</a>
    <a href="classes.php"><i class="fa fa-calendar"></i> My Classes &amp; Grades</a>
    <a href="requests.php"><i class="fa fa-file-text"></i> My Requests</a>
    <a href="student_card.php" class="active"><i class="fa fa-id-card"></i> Student Card</a>
  </nav>

<?php if (!$student): ?>
  <div class="alert alert-warning">
    <i class="fa fa-exclamation-triangle"></i>
    <strong>No student record found.</strong> Please contact the administration office.
  </div>
<?php elseif (!$registrations): ?>
  <div class="alert alert-info">
    <i class="fa fa-info-circle"></i>
    No active registration found. A registration is required to generate a student card.
  </div>
<?php else: ?>

  <?php if (count($registrations) > 1): ?>
  <div class="mb-3" style="max-width:480px;margin:0 auto 20px">
    <label for="reg-select" class="form-label fw-bold">Select Registration:</label>
    <select id="reg-select" class="form-select" onchange="location.href='student_card.php?reg_id='+this.value">
      <?php foreach ($registrations as $r): ?>
        <option value="<?php echo (int)$r->id; ?>" <?php if ($reg && $reg->id == $r->id) echo 'selected'; ?>>
          <?php echo s(trim(($r->program_name ?? '') . ' ' . ($r->program_level ?? ''))); ?>
          (<?php echo s($r->registration_status); ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>

  <!-- Card Preview -->
  <div class="sc-preview-wrap">
    <div class="sc-card">
      <div class="sc-top-bar"></div>
      <div class="sc-inner">
        <div class="sc-header-row">
          <span class="sc-title">Student Card</span>
          <img src="<?php echo $logo_abc; ?>" alt="ABC Horizon" class="sc-abc-logo">
        </div>
        <div class="sc-body-row">
          <div class="sc-photo-col">
            <div class="sc-photo-frame">
              <img id="sc-live-photo"
                   src="<?php echo $photo_url . '&t=' . time(); ?>"
                   alt="Photo"
                   <?php if (empty($student->photo_url)): ?>style="display:none"<?php endif; ?>>
              <span class="sc-photo-placeholder" id="sc-photo-placeholder"
                    <?php if (!empty($student->photo_url)): ?>style="display:none"<?php endif; ?>>
                <i class="fa fa-user"></i>
              </span>
            </div>
            <div class="sc-id-label"><?php echo s($student_id_val); ?></div>
          </div>
          <div class="sc-info-col">
            <div class="sc-field">
              <span class="sc-field-label">Passport Number</span>
              <span class="sc-field-value"><?php echo s($student->national_id ?? '—'); ?></span>
            </div>
            <div class="sc-field">
              <span class="sc-field-label">Student Name</span>
              <span class="sc-field-value"><?php echo s(strtoupper(trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')))); ?></span>
            </div>
            <div class="sc-field">
              <span class="sc-field-label">Academic Program</span>
              <span class="sc-field-value"><?php echo s($academic_program ?: '—'); ?></span>
            </div>
            <div class="sc-field">
              <span class="sc-field-label">Major</span>
              <span class="sc-field-value"><?php echo s($student->major ?? '—'); ?></span>
            </div>
            <div class="sc-field">
              <span class="sc-field-label">Sub Major</span>
              <span class="sc-field-value"><?php echo s($student->sub_major ?? '—'); ?></span>
            </div>
            <div class="sc-field">
              <span class="sc-field-label">Date of Birth</span>
              <span class="sc-field-value"><?php echo s($dob_formatted ?: '—'); ?></span>
            </div>
            <div class="sc-field">
              <span class="sc-field-label">Valid Until</span>
              <span class="sc-field-value"><?php echo s($valid_until ?: '—'); ?></span>
            </div>
          </div>
        </div>
      </div>
      <div class="sc-logos-row">
        <div class="sc-bottom-logos">
          <img src="<?php echo $logo_pearson; ?>" alt="Pearson" class="sc-pearson-logo">
          <img src="<?php echo $logo_btec; ?>" alt="BTEC" class="sc-btec-logo">
        </div>
        <div class="sc-barcode-wrap">
          <svg id="sc-barcode-preview"></svg>
        </div>
      </div>
      <div class="sc-bottom-bar"></div>
    </div>
  </div>

  <!-- Photo upload -->
  <div class="sc-upload-area">
    <label for="sc-photo-input" class="sc-upload-btn">
      <i class="fa fa-camera"></i> Change Photo
    </label>
    <input type="file" id="sc-photo-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
    <?php if ($photo_pending_status === 'pending'): ?>
      <span class="sc-photo-badge pending" id="sc-pending-badge">
        <i class="fa fa-clock-o"></i> Photo awaiting approval from student affairs
      </span>
    <?php elseif ($photo_pending_status === 'rejected'): ?>
      <span class="sc-photo-badge rejected" id="sc-pending-badge">
        <i class="fa fa-times-circle"></i> Photo rejected — please upload a new one
      </span>
    <?php endif; ?>
    <span class="sc-upload-status" id="sc-upload-status"></span>
  </div>

  <!-- Crop modal -->
  <div class="sc-crop-backdrop" id="sc-crop-modal" role="dialog" aria-modal="true" aria-label="Crop Photo">
    <div class="sc-crop-box">
      <div class="sc-crop-header">
        <h3><i class="fa fa-crop"></i> Adjust Your Photo</h3>
        <button class="sc-crop-close" id="sc-crop-cancel" type="button" aria-label="Cancel">&times;</button>
      </div>
      <div class="sc-crop-preview">
        <img id="sc-crop-img" src="" alt="Crop preview">
      </div>
      <div class="sc-crop-controls">
        <span class="sc-crop-hint">Drag to move &bull; Scroll to zoom &bull; 3&times;4 portrait ratio</span>
        <button type="button" id="sc-crop-rotate-l" title="Rotate left"><i class="fa fa-rotate-left"></i> Rotate L</button>
        <button type="button" id="sc-crop-rotate-r" title="Rotate right"><i class="fa fa-rotate-right"></i> Rotate R</button>
        <button type="button" id="sc-crop-zoom-in"  title="Zoom in"><i class="fa fa-search-plus"></i></button>
        <button type="button" id="sc-crop-zoom-out" title="Zoom out"><i class="fa fa-search-minus"></i></button>
        <button type="button" id="sc-crop-reset"    title="Reset"><i class="fa fa-refresh"></i> Reset</button>
        <button type="button" class="sc-crop-confirm" id="sc-crop-confirm"><i class="fa fa-check"></i> Use This Photo</button>
      </div>
    </div>
  </div>

  <!-- Action button -->
  <div class="sc-actions">
    <a href="card_print.php?reg_id=<?php echo (int)$reg->id; ?>&sesskey=<?php echo sesskey(); ?>"
       target="_blank" class="btn btn-primary btn-lg">
      <i class="fa fa-download"></i> Download Student Card (PDF)
    </a>
  </div>

  <div class="alert alert-info" style="max-width:640px;margin:0 auto">
    <i class="fa fa-info-circle"></i>
    Click <strong>Download Student Card</strong> to open a print-ready page.
    Use your browser's <strong>Print</strong> (Ctrl+P) and choose <em>Save as PDF</em> to download.
  </div>

<?php endif; ?>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/barcodes/JsBarcode.code128.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function () {
    // ── Barcode ──────────────────────────────────────────────────────────
    var sid = <?php echo json_encode($student_id_val); ?>;
    if (sid && document.getElementById('sc-barcode-preview')) {
        JsBarcode('#sc-barcode-preview', sid, {
            format: 'CODE128',
            lineColor: '#111111',
            background: 'transparent',
            width: 1.2,
            height: 24,
            displayValue: false,
            margin: 0
        });
    }

    // ── Photo upload + crop ───────────────────────────────────────────────
    var photoInput  = document.getElementById('sc-photo-input');
    var statusEl    = document.getElementById('sc-upload-status');
    var livePhoto   = document.getElementById('sc-live-photo');
    var placeholder = document.getElementById('sc-photo-placeholder');
    var sesskey      = <?php echo json_encode(sesskey()); ?>;
    var uploadUrl    = <?php echo json_encode($CFG->wwwroot . '/local/moodle_zoho_sync/ui/ajax/upload_photo.php'); ?>;

    // Crop modal elements
    var cropModal   = document.getElementById('sc-crop-modal');
    var cropImg     = document.getElementById('sc-crop-img');
    var cropCancel  = document.getElementById('sc-crop-cancel');
    var cropConfirm = document.getElementById('sc-crop-confirm');
    var cropper     = null;
    var originalMime = 'image/jpeg';

    function openCropModal(file) {
        originalMime = file.type || 'image/jpeg';
        var reader = new FileReader();
        reader.onload = function (e) {
            // Destroy previous cropper instance if any
            if (cropper) { cropper.destroy(); cropper = null; }
            cropImg.src = e.target.result;
            cropModal.classList.add('open');
            // Init Cropper.js — 3:4 portrait, fixed aspect ratio
            cropper = new Cropper(cropImg, {
                aspectRatio: 3 / 4,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
            });
        };
        reader.readAsDataURL(file);
    }

    function closeCropModal() {
        cropModal.classList.remove('open');
        if (cropper) { cropper.destroy(); cropper = null; }
        cropImg.src = '';
        photoInput.value = '';
    }

    // Crop controls — guard against missing elements (modal not rendered)
    function bindBtn(id, fn) { var el = document.getElementById(id); if (el) el.addEventListener('click', fn); }
    bindBtn('sc-crop-rotate-l', function () { if (cropper) cropper.rotate(-90); });
    bindBtn('sc-crop-rotate-r', function () { if (cropper) cropper.rotate(90); });
    bindBtn('sc-crop-zoom-in',  function () { if (cropper) cropper.zoom(0.1); });
    bindBtn('sc-crop-zoom-out', function () { if (cropper) cropper.zoom(-0.1); });
    bindBtn('sc-crop-reset',    function () { if (cropper) cropper.reset(); });
    if (cropCancel)  cropCancel.addEventListener('click', closeCropModal);
    if (cropModal)   cropModal.addEventListener('click',  function (e) { if (e.target === cropModal) closeCropModal(); });

    // Confirm crop → upload
    if (cropConfirm) cropConfirm.addEventListener('click', function () {
        if (!cropper) return;
        // Output: 480×640 px (3:4), JPEG quality 0.92
        var canvas = cropper.getCroppedCanvas({ width: 480, height: 640, imageSmoothingQuality: 'high' });
        if (!canvas) { setStatus('Crop failed', 'err'); return; }

        closeCropModal();
        setStatus('<i class="fa fa-spinner fa-spin"></i> Uploading…', '');

        var outMime = (originalMime === 'image/png') ? 'image/png' : 'image/jpeg';
        var outExt  = (outMime === 'image/png') ? 'png' : 'jpg';

        canvas.toBlob(function (blob) {
            if (!blob) { setStatus('Failed to process image', 'err'); return; }
            var fd = new FormData();
            fd.append('sesskey', sesskey);
            fd.append('photo', blob, 'photo.' + outExt);

            fetch(uploadUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        var badge = document.getElementById('sc-pending-badge');
                        if (badge) {
                            badge.className = 'sc-photo-badge pending';
                            badge.innerHTML = '<i class="fa fa-clock-o"></i> Photo awaiting approval from student affairs';
                        } else {
                            var newBadge = document.createElement('span');
                            newBadge.id = 'sc-pending-badge';
                            newBadge.className = 'sc-photo-badge pending';
                            newBadge.innerHTML = '<i class="fa fa-clock-o"></i> Photo awaiting approval from student affairs';
                            statusEl.parentNode.insertBefore(newBadge, statusEl);
                        }
                        var msg = 'Request submitted — your current photo remains until approved.';
                        if (data.zoho_error) {
                            msg += ' <span style="color:#e65100">(Zoho: ' + data.zoho_error + ')</span>';
                        }
                        setStatus(msg, 'ok');
                    } else {
                        setStatus('Upload failed: ' + (data.error || 'Unknown error'), 'err');
                    }
                })
                .catch(function (err) { setStatus('Network error: ' + err, 'err'); });
        }, outMime, 0.92);
    });

    // File selected → open crop modal
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var maxMB = 10; // allow larger originals before crop
            if (file.size > maxMB * 1024 * 1024) {
                setStatus('File too large (max ' + maxMB + ' MB)', 'err');
                this.value = '';
                return;
            }
            openCropModal(file);
        });
    }

    function setStatus(html, cls) {
        if (!statusEl) return;
        statusEl.className = 'sc-upload-status' + (cls ? ' ' + cls : '');
        statusEl.innerHTML = html;
    }
})();
</script>

<?php
echo $OUTPUT->footer();
?>
