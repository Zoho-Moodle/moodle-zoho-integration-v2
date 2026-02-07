<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

global $DB, $CFG;

// استلام النص من البحث
$q = trim(optional_param('q', '', PARAM_TEXT));
if ($q === '') exit;

// تحديد نوع البحث بناءً على شكل النص
$isEmailLike = (str_contains($q, '@') || str_contains($q, '.') || str_contains($q, '_'));
$isNumeric = preg_match('/^\d+$/', $q);

// الأعمدة
$select = "u.id,
           u.username,
           u.firstname,
           u.lastname,
           p.display_name,
           p.academic_email,
           p.status,
           p.country";

$from = "{user} u
          LEFT JOIN {student_profile} p ON p.userid = u.id";

$where = "";
$params = [];

if ($isEmailLike) {
    // 🔹 بحث بالإيميل أو اليوزرنيم
    $where = "(" .
             $DB->sql_like('u.username', ':email1', false) . " OR " .
             $DB->sql_like('p.academic_email', ':email2', false) . ")" .
             " AND u.username NOT LIKE '%unknownemail.invalid%'";
    $params['email1'] = "%$q%";
    $params['email2'] = "%$q%";

} elseif ($isNumeric) {
    // 🔹 بحث بالـ ID أو Zoho ID
    $where = "(u.id = :id OR p.zoho_id = :zid)";
    $params['id'] = $q;
    $params['zid'] = $q;

} else {
    // 🔹 بحث بالأسماء + اليوزرنيم
    $terms = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
    $nameConditions = [];
    $i = 1;
    foreach ($terms as $word) {
        $like1 = $DB->sql_like('p.display_name', ":d$i", false);
        $like2 = $DB->sql_like('u.firstname', ":f$i", false);
        $like3 = $DB->sql_like('u.lastname', ":l$i", false);
        $nameConditions[] = "($like1 OR $like2 OR $like3)";
        $params["d$i"] = "%$word%";
        $params["f$i"] = "%$word%";
        $params["l$i"] = "%$word%";
        $i++;
    }
    $where = implode(' AND ', $nameConditions);

    // نضيف بحث إضافي في اليوزرنيم (احتياطي)
    $where .= " OR " . $DB->sql_like('u.username', ':uname', false);
    $params['uname'] = "%$q%";
}

// تنفيذ الاستعلام
$sql = "SELECT $select FROM $from WHERE $where ORDER BY u.firstname ASC LIMIT 15";
$results = $DB->get_records_sql($sql, $params);

if (!$results) {
    echo "<div style='padding:10px;text-align:center;opacity:.6'>No results found</div>";
    exit;
}

// ✅ سكربت بسيط لتفعيل التنقل بالكيبورد
echo "<script>
document.querySelectorAll('.student-result-item').forEach(e=>e.remove());
let items = [];
let selectedIndex = -1;

function moveSelection(dir) {
  if (!items.length) return;
  selectedIndex = (selectedIndex + dir + items.length) % items.length;
  items.forEach((it, idx)=>it.style.background = idx===selectedIndex ? '#eef' : 'transparent');
}

function openSelected() {
  if (selectedIndex>=0 && items[selectedIndex]) {
    window.location.href = items[selectedIndex].dataset.url;
  }
}

document.addEventListener('keydown', (ev)=>{
  if(ev.key==='ArrowDown'){moveSelection(1);}
  else if(ev.key==='ArrowUp'){moveSelection(-1);}
  else if(ev.key==='Enter'){openSelected();}
});
</script>";

foreach ($results as $r) {
    // ✅ اسم العرض
    $name = $r->display_name ?: trim($r->firstname . ' ' . $r->lastname);
    if ($name === '') $name = $r->username;

    // ✅ الإيميل الأنسب
    $email = $r->academic_email ?: (
        str_contains($r->username, '@unknownemail.invalid') ? '' : $r->username
    );

    // ✅ عناصر إضافية
    $status = $r->status ? "<span style='background:#eef;padding:2px 6px;border-radius:6px;font-size:.8em;color:#334;'>".s($r->status)."</span>" : '';
    $country = $r->country ? "<span style='opacity:.7;font-size:.8em;'>🌍 ".s($r->country)."</span>" : '';
    $url = $CFG->wwwroot . '/local/mb_zoho_sync/student_dashboard.php?userid=' . $r->id;

    // ✅ إخراج النتيجة
    echo "<div class='student-result-item' data-url='$url'
            style='padding:10px 14px;cursor:pointer;border-bottom:1px solid #eee;
                   transition:0.15s;display:flex;flex-direction:column;gap:4px'
            onmouseover=\"this.style.background='#f0f7ff'\"
            onmouseout=\"this.style.background='transparent'\"
            onclick=\"location.href='$url'\"> 
            <div style='font-weight:600;font-size:1em;color:#223'>".s($name)."</div>
            <div style='font-size:.85em;opacity:.8'>".s($email)."</div>
            <div style='display:flex;gap:10px;margin-top:2px'>$status $country</div>
          </div>";
}

// ✅ بعد طباعة النتائج نعرّف العناصر ضمن JS
echo "<script>
items = document.querySelectorAll('.student-result-item');
if(items.length) {
  selectedIndex = 0;
  items[0].style.background = '#eef';
}
</script>";
