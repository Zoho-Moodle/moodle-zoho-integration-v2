<?php
require('../../config.php');
require_login();
global $DB;

$courses = $DB->get_records_sql("
    SELECT DISTINCT cm.course
    FROM {course_modules} cm
    WHERE cm.module = (
        SELECT id FROM {modules} WHERE name = 'url'
    )
");

foreach ($courses as $course) {
    $cmids = $DB->get_fieldset_sql("
        SELECT cm.id
        FROM {course_modules} cm
        JOIN {url} u ON u.id = cm.instance
        WHERE cm.course = ? AND cm.module = (SELECT id FROM {modules} WHERE name = 'url')
        ORDER BY cm.id ASC
    ", [$course->course]);

    // حدد القسم المستهدف (section رقم 0 مثلاً أو 1)
    $section = $DB->get_record('course_sections', ['course' => $course->course, 'section' => 1]);

    if ($section && !empty($cmids)) {
        $sequence = explode(",", $section->sequence);
        $newcmids = array_diff($cmids, $sequence); // فقط cmid غير الموجود سابقًا
        if (!empty($newcmids)) {
            $section->sequence = implode(",", array_merge($sequence, $newcmids));
            $DB->update_record('course_sections', $section);
            echo "<p style='color:green;'>✅ تم تحديث ترتيب الأنشطة في الكورس ID: {$course->course}</p>";
        } else {
            echo "<p style='color:orange;'>⏩ الكورس ID: {$course->course} يحتوي بالفعل على كل الروابط.</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ لم يتم العثور على القسم المناسب للكورس ID: {$course->course}</p>";
    }
}
?>
