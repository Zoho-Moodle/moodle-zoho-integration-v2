<?php
// local/mb_zoho_sync/push_recordings.php

// نتحقق إذا التشغيل من CLI قبل تعريف الثابت
if (php_sapi_name() === 'cli') {
    define('CLI_SCRIPT', true);
}

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/resourcelib.php');

global $DB, $PAGE, $OUTPUT;

// ======= كشف بيئة التشغيل =======
$iscli  = (php_sapi_name() === 'cli');
$isajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// ======= تهيئة بيئة الويب =======
if (!$iscli && !$isajax) {
    require_login();
    require_capability('moodle/site:config', context_system::instance());
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/mb_zoho_sync/push_recordings.php', ['sesskey' => sesskey()]));
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title('Push Recordings Links');
    $PAGE->set_heading('Push Recordings Links');
    echo $OUTPUT->header();
    echo html_writer::tag('h3', "📎 Pushing 'Recordings' URLs...", ['style' => 'margin:10px 0 20px']);
}

// ======= دوال مساعدة =======
function normalize_url_local(string $u): string {
    $u = trim($u);
    if ($u === '') return '';
    $parts = parse_url($u);
    if (!$parts) return strtolower($u);
    $scheme = strtolower($parts['scheme'] ?? 'https');
    $host   = strtolower($parts['host']   ?? '');
    $path   = $parts['path'] ?? '';
    $query  = isset($parts['query']) ? ('?'.$parts['query']) : '';
    return "{$scheme}://{$host}{$path}{$query}";
}

function create_recordings_activity(int $courseid, string $link): int {
    global $DB;
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $module = $DB->get_record('modules', ['name' => 'url'], '*', MUST_EXIST);

    $moduleinfo = new stdClass();
    $moduleinfo->modulename  = 'url';
    $moduleinfo->module      = $module->id;
    $moduleinfo->course      = $course->id;
    $moduleinfo->section     = 0;
    $moduleinfo->visible     = 1;
    $moduleinfo->name        = 'Recordings';
    $moduleinfo->intro       = 'This is the SharePoint recordings folder.';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->externalurl = $link;
    $moduleinfo->display     = RESOURCELIB_DISPLAY_OPEN;

    $newmod = add_moduleinfo($moduleinfo, $course, null);
    return $newmod->coursemodule;
}

// ======= تنفيذ رئيسي =======
$sql = "SELECT ss.courseid, ss.teamname, ss.sharepointlink
        FROM {sync_sharepoint} ss
        JOIN {course} c ON c.id = ss.courseid
        ORDER BY ss.courseid ASC";
$records = $DB->get_records_sql($sql);

$created = $updated = $unchanged = $skipped = 0;

$print = function($msg) use ($iscli, $isajax) {
    if ($iscli) {
        echo $msg . "\n";
    } elseif ($isajax) {
        // لا شيء هنا لتجنب فوضى المخرجات
    } else {
        echo html_writer::tag('div', $msg, ['style' => 'margin:3px 0;']);
    }
};

$print("🔎 Processing " . count($records) . " courses...");

foreach ($records as $r) {
    $cid = (int)$r->courseid;
    $link = trim($r->sharepointlink);
    $team = $r->teamname ?? '';

    if (empty($link)) {
        $skipped++;
        $print("($cid) $team — ⏭️ لا يوجد رابط");
        continue;
    }

    $normlink = normalize_url_local($link);

    // نبحث فقط عن Activity فعّالة وغير محذوفة
    $existing = $DB->get_record_sql("
        SELECT u.*, cm.id AS cmid
        FROM {url} u
        JOIN {course_modules} cm ON cm.instance = u.id
        JOIN {modules} m ON m.id = cm.module AND m.name = 'url'
        WHERE u.name = 'Recordings'
          AND cm.course = ?
          AND cm.deletioninprogress = 0
          AND cm.visible = 1
        LIMIT 1
    ", [$cid]);

    // في حال غير موجود أو تم حذفه → نعيد إنشاؤه
    if (!$existing || !$DB->record_exists('course_modules', ['id' => $existing->cmid])) {
        try {
            $cmid = create_recordings_activity($cid, $link);
            $created++;
            $print("($cid) 🆕 تم إنشاء Activity جديد (cmid=$cmid)");
        } catch (Throwable $e) {
            $skipped++;
            $print("($cid) ❌ خطأ أثناء الإنشاء: " . $e->getMessage());
        }
    } else {
        // موجود فعّال → نتحقق من الرابط
        $currnorm = normalize_url_local($existing->externalurl);
        if ($currnorm === $normlink) {
            $unchanged++;
            $print("($cid) نفس الرابط — ⏩ لا تعديل");
        } else {
            $existing->externalurl = $link;
            $existing->timemodified = time();
            $DB->update_record('url', $existing);
            $updated++;
            $print("($cid) ♻️ تم تحديث الرابط");
        }
    }

    // تحديث حالة pushed في sync_sharepoint
    try {
        $DB->execute("UPDATE {sync_sharepoint}
                      SET pushed = 1, status = 'ok', timecreated = ?
                      WHERE courseid = ?", [time(), $cid]);
    } catch (Throwable $e) {
        $print("⚠️ فشل تحديث pushed: " . $e->getMessage());
    }
}

// ======= الخلاصة =======
$summary = "
===== Summary =====
✅ Created   : {$created}
♻️ Updated   : {$updated}
⏩ Unchanged : {$unchanged}
⏭️ Skipped   : {$skipped}
";

if ($iscli) {
    echo $summary;
} elseif ($isajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'created'   => $created,
        'updated'   => $updated,
        'unchanged' => $unchanged,
        'skipped'   => $skipped,
        'total'     => count($records),
        'status'    => 'ok'
    ]);
} else {
    echo html_writer::tag('pre', $summary);
    echo $OUTPUT->footer();
}
