<?php
/**
 * Student Card – Standalone Print / PDF page
 *
 * No Moodle header/footer. Auto-triggers browser print dialog.
 * Student saves as PDF from the browser's print dialog.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_login();

// ── Validate sesskey & params ───────────────────────────────────────────────
$sesskey = required_param('sesskey', PARAM_RAW);
$reg_id  = required_param('reg_id',  PARAM_INT);

if (!confirm_sesskey($sesskey)) {
    die('Invalid sesskey');
}

$userid = $USER->id;

// ── Load student ────────────────────────────────────────────────────────────
$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$userid]
);
if (!$student) {
    die('Student record not found');
}

// ── Load registration (must belong to this student) ─────────────────────────
$reg = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_registrations} WHERE id = ? AND student_id = ? LIMIT 1",
    [$reg_id, $student->id]
);
if (!$reg) {
    die('Registration not found');
}

// ── Derived fields ──────────────────────────────────────────────────────────
$rd               = strtotime($reg->registration_date);
$valid_until      = $rd ? date('M j, Y', strtotime('+2 years', $rd)) : '—';
$academic_program = trim(($reg->program_name ?? '') . ' ' . ($reg->program_level ?? '')) ?: '—';

$dob_formatted = '—';
if (!empty($student->date_of_birth)) {
    $ts = strtotime($student->date_of_birth);
    if ($ts) $dob_formatted = date('M j, Y', $ts);
}

$student_name   = strtoupper(trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')));
$student_id_val = $student->student_id ?: ($student->zoho_student_id ?: '');
$passport_no    = $student->national_id ?? '—';
$major          = $student->major     ?? '—';
$sub_major      = $student->sub_major ?? '—';

$logo_abc     = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/ABC%20Horizon%20Logo%20Full%20Color.png';
$logo_pearson = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/Pearson.png';
$logo_btec    = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/BTEC.png';
$photo_url    = (new moodle_url('/local/moodle_zoho_sync/ui/student/serve_photo.php', ['uid' => $userid]))->out(false);

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Card – <?php echo htmlspecialchars($student_name); ?></title>
<style>
/* ── Reset ─────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: #e8e8e8;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    font-family: 'Segoe UI', Arial, sans-serif;
}

/* ── No-print toolbar ──────────────────────────────────── */
.no-print {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 28px;
    padding: 14px 24px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
.no-print button {
    padding: 10px 28px;
    font-size: 15px;
    font-weight: 700;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: #F15A24;
    color: #fff;
    letter-spacing: .04em;
    transition: background .2s;
}
.no-print button:hover { background: #d44a18; }
.no-print a {
    font-size: 13px;
    color: #555;
    text-decoration: none;
    padding: 6px 14px;
    border: 1px solid #ccc;
    border-radius: 6px;
}
.no-print a:hover { background: #f5f5f5; }

/* ── Card ──────────────────────────────────────────────── */
.sc-card {
    width: 620px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 6px 32px rgba(0,0,0,.22);
    background: #fff;
    position: relative;
    border-top: 3px solid #111;
    border-bottom: 3px solid #111;
    display: flex;
    flex-direction: column;
}
.sc-top-bar {
    height: 11px;
    background: linear-gradient(90deg, #F15A24 0%, #F15A24 33.33%, #1565C0 33.33%, #1565C0 66.66%, #1E6B2D 66.66%, #1E6B2D 100%);
    flex-shrink: 0;
}
.sc-inner { padding: 16px 20px 12px; flex: 1; }

/* ── Header ────────────────────────────────────────────── */
.sc-header-row {
    position: relative;
    margin-bottom: 2px;
    min-height: 28px;
}
.sc-title {
    font-size: 15px;
    font-weight: 800;
    letter-spacing: .08em;
    color: #1a1a1a;
    text-transform: uppercase;
    padding-top: 4px;
    display: block;
}
.sc-abc-logo { position: absolute; top: 0; right: 0; height: 86px; object-fit: contain; }

/* ── Body ──────────────────────────────────────────────── */
.sc-body-row { display: flex; gap: 20px; align-items: flex-start; }

.sc-photo-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 108px;
}
.sc-photo-frame {
    width: 108px;
    height: 135px;
    border: 2px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sc-photo-frame img { width: 100%; height: 100%; object-fit: cover; }
.sc-photo-placeholder { font-size: 52px; color: #bbb; }
.sc-id-label {
    margin-top: 6px;
    font-size: 10px;
    font-weight: 700;
    color: #444;
    letter-spacing: .06em;
    text-align: center;
}

/* ── Info fields ───────────────────────────────────────── */
.sc-info-col { flex: 1; }
.sc-field { display: flex; margin-bottom: 7px; align-items: baseline; }
.sc-field-label {
    font-size: 10px;
    color: #888;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    min-width: 140px;
}
.sc-field-value {
    font-size: 12px;
    font-weight: 700;
    color: #1a1a1a;
    flex: 1;
}

/* ── Bottom bar ────────────────────────────────────────── */
.sc-logos-row {
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    flex-shrink: 0;
}
.sc-bottom-logos { display: flex; align-items: center; gap: 14px; }
.sc-pearson-logo, .sc-btec-logo {
    height: 26px;
    object-fit: contain;
}
.sc-barcode-wrap svg { height: 28px; display: block; }
.sc-bottom-bar {
    height: 11px;
    background: linear-gradient(90deg, #F15A24 0%, #F15A24 33.33%, #1565C0 33.33%, #1565C0 66.66%, #1E6B2D 66.66%, #1E6B2D 100%);
    flex-shrink: 0;
}

/* ── Print styles ──────────────────────────────────────── */
@media print {
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    @page {
        size: 88mm 56mm;   /* CR80 card size */
        margin: 0;
    }
    html, body {
        width: 88mm;
        height: 56mm;
        background: #fff !important;
        display: block;
    }
    .no-print { display: none !important; }
    .sc-card {
        width: 88mm;
        border-radius: 0;
        box-shadow: none;
    }
    .sc-top-bar { height: 8px; }
    .sc-inner { padding: 10px 12px 0; }
    .sc-header-row { margin-bottom: 1px; min-height: 28px; }
    .sc-title { font-size: 9px; }
    .sc-abc-logo { position: absolute; top: 0; right: 0; height: 28px; }
    .sc-body-row { gap: 10px; }
    .sc-photo-col { min-width: 62px; }
    .sc-photo-frame { width: 62px; height: 80px; }
    .sc-id-label { font-size: 7px; margin-top: 3px; }
    .sc-field { margin-bottom: 3px; }
    .sc-field-label { font-size: 6px; min-width: 80px; }
    .sc-field-value { font-size: 7px; }
    .sc-bottom-bar { height: 8px; }
    .sc-logos-row { padding: 4px 10px; }
    .sc-pearson-logo, .sc-btec-logo { height: 16px; }
    .sc-bottom-logos { gap: 8px; }
    .sc-barcode-wrap svg { height: 18px; }
    .sc-photo-placeholder { font-size: 28px; }
}
</style>
</head>
<body>

<!-- Toolbar (hidden on print) -->
<div class="no-print">
    <button onclick="window.print()">⬇ Download / Print as PDF</button>
    <a href="student_card.php">← Back</a>
</div>

<!-- The Card -->
<div class="sc-card">
    <div class="sc-top-bar"></div>
    <div class="sc-inner">
        <div class="sc-header-row">
            <span class="sc-title">Student Card</span>
            <img src="<?php echo htmlspecialchars($logo_abc); ?>" alt="ABC Horizon" class="sc-abc-logo">
        </div>
        <div class="sc-body-row">
            <div class="sc-photo-col">
                <div class="sc-photo-frame">
                    <?php if (!empty($student->photo_url)): ?>
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Student Photo">
                    <?php else: ?>
                        <span class="sc-photo-placeholder">&#9786;</span>
                    <?php endif; ?>
                </div>
                <div class="sc-id-label"><?php echo htmlspecialchars($student_id_val); ?></div>
            </div>
            <div class="sc-info-col">
                <div class="sc-field">
                    <span class="sc-field-label">Passport Number</span>
                    <span class="sc-field-value"><?php echo htmlspecialchars($passport_no); ?></span>
                </div>
                <div class="sc-field">
                    <span class="sc-field-label">Student Name</span>
                    <span class="sc-field-value"><?php echo htmlspecialchars($student_name); ?></span>
                </div>
                <div class="sc-field">
                    <span class="sc-field-label">Academic Program</span>
                    <span class="sc-field-value"><?php echo htmlspecialchars($academic_program); ?></span>
                </div>
                <div class="sc-field">
                    <span class="sc-field-label">Major</span>
                    <span class="sc-field-value"><?php echo htmlspecialchars($major); ?></span>
                </div>
                <div class="sc-field">
                    <span class="sc-field-label">Sub Major</span>
                    <span class="sc-field-value"><?php echo htmlspecialchars($sub_major); ?></span>
                </div>
                <div class="sc-field">
                    <span class="sc-field-label">Date of Birth</span>
                    <span class="sc-field-value"><?php echo htmlspecialchars($dob_formatted); ?></span>
                </div>
                <div class="sc-field">
                    <span class="sc-field-label">Valid Until</span>
                    <span class="sc-field-value"><?php echo htmlspecialchars($valid_until); ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="sc-logos-row">
        <div class="sc-bottom-logos">
            <img src="<?php echo htmlspecialchars($logo_pearson); ?>" alt="Pearson" class="sc-pearson-logo">
            <img src="<?php echo htmlspecialchars($logo_btec); ?>" alt="BTEC" class="sc-btec-logo">
        </div>
        <div class="sc-barcode-wrap">
            <svg id="sc-barcode"></svg>
        </div>
    </div>
    <div class="sc-bottom-bar"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/barcodes/JsBarcode.code128.min.js"></script>
<script>
(function () {
    var sid = <?php echo json_encode($student_id_val); ?>;
    if (sid) {
        JsBarcode('#sc-barcode', sid, {
            format: 'CODE128',
            lineColor: '#111111',
            background: 'transparent',
            width: 1.2,
            height: 24,
            displayValue: false,
            margin: 0
        });
    }
    // Auto-open print dialog after a short delay so the barcode renders first
    setTimeout(function () { window.print(); }, 600);
})();
</script>
</body>
</html>
