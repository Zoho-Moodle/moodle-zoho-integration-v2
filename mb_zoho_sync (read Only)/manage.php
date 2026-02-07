<?php
require('../../config.php');
require_login();

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

$context   = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mb_zoho_sync/manage.php'));
$hasmanage = has_capability('local/mb_zoho_sync:manage', $context);

$PAGE->set_title('ABC MB Sync Toolkit');
$PAGE->set_heading('ABC MB Sync Toolkit');

include(__DIR__ . '/style_manage.php');

function print_finance_table($fields, $finance) {
    echo '<table class="generaltable" style="width: 100%; max-width: 700px;">';
    echo '<colgroup><col style="width: 20%; background-color: #f9f9f9;"><col style="width: 80%;"></colgroup>';
    echo '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
    foreach ($fields as $f) {
        $value = $finance ? ($finance->$f ?? '') : '';
        echo '<tr>';
        echo '<td style="font-weight: bold;">' . ucfirst(str_replace('_', ' ', $f)) . '</td>';
        echo '<td data-field="' . $f . '">' . htmlspecialchars($value) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/**
 * يرسم tbody فقط لجدول SharePoint بدون أي اعتماد على $PAGE/format_string
 */
function render_sharepoint_tbody(array $rows): string {
    $out = '';
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $pushed = ($row->pushed ?? 0) ? '✅ تم التحديث' : '✅ تم انشاء الرابط';
            $courseid = (int)($row->courseid ?? 0);
            $teamname = htmlspecialchars($row->teamname ?? '', ENT_QUOTES, 'UTF-8');
            $objectid = htmlspecialchars($row->objectid ?? '', ENT_QUOTES, 'UTF-8');
            $status   = htmlspecialchars($row->status   ?? '', ENT_QUOTES, 'UTF-8');
            $link     = htmlspecialchars($row->sharepointlink ?? '', ENT_QUOTES, 'UTF-8');
            $linkcol  = $link ? "<a href='{$link}' target='_blank'>فتح الرابط</a>" : '-';

            $out .= "<tr>
                <td>{$courseid}</td>
                <td>{$teamname}</td>
                <td>{$objectid}</td>
                <td>{$status}</td>
                <td>{$linkcol}</td>
                <td>{$pushed}</td>
            </tr>";
        }
    } else {
        $out = '<tr><td colspan="6" style="text-align:center;">No data available.</td></tr>';
    }
    return $out;
}

/* ====== فرع partial: يرجّع tbody فقط ويخرج فورًا ====== */
$partial = optional_param('partial', '', PARAM_ALPHA);
if ($partial === 'sharepoint') {
    global $DB;
    $rows = $DB->get_records('sync_sharepoint', null, 'timecreated DESC');
    echo render_sharepoint_tbody($rows);
    exit;
}

/* ====== بقيّة الصفحة الكاملة ====== */

$search   = optional_param('search', '', PARAM_TEXT);
$user     = null;
$finance  = null;

if ($search) {
    if (is_numeric($search)) {
        $user = $DB->get_record('user', ['id' => $search, 'deleted' => 0]);
    } else {
        $like   = '%' . $DB->sql_like_escape($search) . '%';
        $params = [$like, $like, $like, $like];
        $sql = "SELECT id, username, firstname, lastname,
                       firstnamephonetic, lastnamephonetic, middlename, alternatename
                FROM {user}
                WHERE deleted = 0 AND (
                    username LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?
                ) LIMIT 10";
        $users = $DB->get_records_sql($sql, $params);

        if (count($users) == 1) {
            $user = reset($users);
        } elseif (count($users) > 1) {
            echo $OUTPUT->header();
            echo '<div style="margin: 40px;"><h5>🔍 Search Students:</h5><ul>';
            foreach ($users as $u) {
                $url = new moodle_url('/local/mb_zoho_sync/manage.php', ['search' => $u->id]);
                echo '<li><a href="' . $url . '">' . fullname($u) . ' (' . $u->username . ')</a></li>';
            }
            echo '</ul></div>';
            echo $OUTPUT->footer();
            exit;
        }
    }

    if ($user) {
        $finance = $DB->get_record('financeinfo', ['userid' => $user->id]);
    }
}

$userid = $user ? $user->id : 0;

echo $OUTPUT->header();
?>

<!-- 🔵 Form Search -->
<div class="section-title">📁 Sync Finance Info <button class="toggle-btn" onclick="toggleSection('finance-content', this)">⬆️</button></div>
<div class="section-content" id="finance-content">

<?php if ($hasmanage): ?>
<form method="get" action="manage.php" class="search-box">
    <input type="text" name="search" placeholder="Search by name or username..." value="<?= s($search) ?>" />
    <button type="submit">Search</button>
</form>
<?php endif; ?>

<h4 style="text-align:center; margin:20px;">Student: <?= $user ? fullname($user) : 'No student selected' ?></h4>

<div class="finance-wrapper">
<?php
$fields = [
    'scholarship','scholarship_reason','scholarship_percentage',
    'currency','amount_transferred','payment_method','payment_mode',
    'bank_name','bank_holder','registration_fees','invoice_reg_fees',
    'total_amount','discount_amount','zoho_id'
];
print_finance_table(array_slice($fields, 0, 7), $finance);
print_finance_table(array_slice($fields, 7),    $finance);
?>
</div>

<!-- 🔵 Payments Table -->
<h5 style="text-align:center;">Payments</h5>
<table class="generaltable" id="paymentstable" style="border-collapse: collapse; width: 100%;">
<thead>
<tr>
    <th>Payment Name</th><th>Amount</th><th>Date</th><th>Invoice Number</th><th>Notes</th>
</tr>
</thead>
<tbody id="payments-body">
<?php
if ($finance) {
    $payments = $DB->get_records('financeinfo_payments', ['financeinfoid' => $finance->id]);
    if ($payments) {
        foreach ($payments as $p) {
            echo '<tr id="row-' . $p->id . '">
                <td>' . htmlspecialchars($p->payment_name) . '</td>
                <td>' . htmlspecialchars($p->amount)       . '</td>
                <td>' . date('Y-m-d', $p->payment_date)   . '</td>
                <td>' . htmlspecialchars($p->invoice_number) . '</td>
                <td>' . htmlspecialchars($p->notes ?? '')  . '</td>
            </tr>';
        }
    } else {
        echo '<tr><td colspan="5" style="text-align:center;">No payments found.</td></tr>';
    }
} else {
    echo '<tr><td colspan="5" style="text-align:center;">No payments found.</td></tr>';
}
?>
</tbody>
</table>

<!-- 🔵 Sync Finance Button -->
<?php if ($user && $userid): ?>
<div style="text-align:center; margin: 40px 0;">
    <button id="sync-finance-btn" class="btn btn-success"
            style="font-size:18px; padding:10px 30px;">🔄 Sync Finance</button>
</div>
<?php endif; ?>

</div>

<!-- 🔵 Divider -->
<div class="section-divider"></div>

<div class="section-title">🔗 Sync Teams Recordings <button class="toggle-btn" onclick="toggleSection('sharepoint-section', this)">⬆️</button></div>
<div class="section-content" id="sharepoint-section">
  <div class="sync-section" style="text-align:center;">
    <button id="sync-sharepoint-btn">🔄 Sync Now</button>
    <button id="push-recordings-btn">📤 Push Recordings</button>
  </div>
  <div id="sharepoint-result" style="margin-top: 30px; text-align:center;">
  <?php
  $rows = $DB->get_records('sync_sharepoint', null, 'timecreated DESC');
  echo '<table class="sharepoint-table"><thead><tr>
      <th>Course ID</th><th>Team Name</th><th>Object ID</th>
      <th>Sync Status</th><th>SharePoint Link</th><th>Push Status</th>
    </tr></thead><tbody id="sharepoint-body">';
  echo render_sharepoint_tbody($rows);
  echo '</tbody></table>';
  ?>
  </div>
</div>

<!-- 🔵 Divider -->
<div class="section-divider"></div>

<div class="section-title">📝 Grading Report <button class="toggle-btn" onclick="toggleSection('grading-section', this)">⬆️</button></div>
<div class="section-content" id="grading-section">
  <div style="overflow-x:auto; max-width:100%;">
    <table class="generaltable" style="width:100%; text-align:center;">
    <thead>
    <tr>
      <th>Student Name</th><th>Course</th><th>Grade</th>
      <th>Status</th><th>Grader</th><th>Role</th><th>Date/Time</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $gradinglogfile = __DIR__ . '/grading_log.json';
    if (file_exists($gradinglogfile)) {
        $json    = file_get_contents($gradinglogfile);
        $entries = json_decode($json, true);
        $entries = array_slice(array_reverse($entries), 0, 30);
        foreach ($entries as $entry) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry['student_name']) . '</td>';
            echo '<td>' . htmlspecialchars($entry['course_name'])  . '</td>';
            echo '<td>' . htmlspecialchars($entry['grade'])        . '</td>';
            echo '<td>' . htmlspecialchars($entry['status'])       . '</td>';
            echo '<td>' . htmlspecialchars($entry['grader'])       . '</td>';
            echo '<td>' . htmlspecialchars($entry['grader_role'])  . '</td>';
            echo '<td>' . htmlspecialchars($entry['timestamp'])    . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">No grading logs found.</td></tr>';
    }
    ?>
    </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('sync-btec-btn')?.addEventListener('click', () => {
  const btn = document.getElementById('sync-btec-btn');
  const message = document.getElementById('btec-sync-message');
  const tableWrapper = document.getElementById('btec-template-table-wrapper');
  const original = btn.innerHTML;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';
  message.innerText = '';
  message.style.color = '';

  fetch('fetch_templates_from_zoho.php')
    .then(res => res.ok ? res.json() : Promise.reject())
    .then(data => {
      if (data.status === 'success') {
        btn.style.backgroundColor = '#4CAF50';
        message.innerText = '✅ BTEC templates synced successfully.';
        message.style.color = '#4CAF50';

        fetch(window.location.pathname + '?btec_page=1 #btec-template-table-wrapper')
          .then(res => res.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector('#btec-template-table-wrapper').innerHTML;
            tableWrapper.innerHTML = newContent;
          });

      } else {
        btn.style.backgroundColor = '#f44336';
        message.innerText = '❌ Sync failed.';
        message.style.color = '#f44336';
      }
    })
    .catch(() => {
      btn.style.backgroundColor = '#f44336';
      message.innerText = '❌ Error occurred during sync.';
      message.style.color = '#f44336';
    })
    .finally(() => {
      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = original;
        btn.style.backgroundColor = '';
        setTimeout(() => {
          message.innerText = '';
        }, 5000);
      }, 2000);
    });
});
</script>

<!-- 🔵 Scripts -->
<script>
function toggleSection(id, btn) {
  const el = document.getElementById(id);
  if (el.style.display === 'none') {
    el.style.display = 'block'; btn.innerText = '⬆️';
  } else {
    el.style.display = 'none';  btn.innerText = '⬇️';
  }
}


/* تحديث جدول SharePoint بعد Push */
function refreshSharepointTable() {
  fetch('manage.php?partial=sharepoint')
    .then(r => r.text())
    .then(html => { document.getElementById('sharepoint-body').innerHTML = html; });
}

// ✅ دالة موحدة لتنفيذ زر عبر AJAX (تبقى كما هي للأزرار الأخرى)
function handleSyncButton(btnId, url) {
  const btn = document.getElementById(btnId);
  if (!btn) return;
  const original = btn.innerHTML;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';

  fetch(url)
    .then(res => res.ok ? Promise.resolve() : Promise.reject())
    .then(() => { btn.style.backgroundColor = '#4CAF50'; })
    .catch(() => { btn.style.backgroundColor = '#f44336'; })
    .finally(() => {
      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = original;
        btn.style.backgroundColor = '';
      }, 2000);
    });
}

// ✅ زر Sync SharePoint (يبقى كما هو)
document.getElementById('sync-sharepoint-btn')?.addEventListener('click', () => {
  handleSyncButton('sync-sharepoint-btn', 'sync_sharepoint.php');
});

// ✅ زر Push Recordings — استدعاء AJAX ثم إنعاش الجدول
document.getElementById('push-recordings-btn')?.addEventListener('click', () => {
  const btn = document.getElementById('push-recordings-btn');
  const original = btn.innerHTML;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';

  fetch('push_recordings.php?ajax=1')
    .then(res => res.ok ? res.text() : Promise.reject())
    .then(() => {
      btn.style.backgroundColor = '#4CAF50';
      return refreshSharepointTable();
    })
    .catch(() => {
      btn.style.backgroundColor = '#f44336';
    })
    .finally(() => {
      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = original;
        btn.style.backgroundColor = '';
      }, 1200);
    });
});
</script>

<!-- ✅ Spinner CSS -->
<style>
.spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid #f3f3f3;
  border-top: 2px solid #555;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  vertical-align: middle;
}
@keyframes spin {
  100% { transform: rotate(360deg); }
}
.btn-force-sync {
  border: none;
  background: none;
  cursor: pointer;
  font-size: 18px;
}
.field-updated {
  background-color: #d4edda !important;
  transition: background-color 1s ease;
}
</style>

<?php
echo $OUTPUT->footer();
