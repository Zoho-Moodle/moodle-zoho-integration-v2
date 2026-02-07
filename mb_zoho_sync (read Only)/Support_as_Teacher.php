<?php
@ini_set('display_errors', 1);
@error_reporting(E_ALL);

// المسار الصحيح إلى config.php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

// إعدادات المستخدم
$userid = 8157;
$roleid = 3;
$enrolmethod = 'manual';
$count = 0;

// جلب كل الكورسات المرئية
$courses = $DB->get_records('course', ['visible' => 1]);

foreach ($courses as $course) {
    if ($course->id == 1) continue;

    $context = context_course::instance($course->id);
    $enrolinstances = enrol_get_instances($course->id, true);

    foreach ($enrolinstances as $instance) {
        if ($instance->enrol == $enrolmethod) {
            $plugin = enrol_get_plugin($enrolmethod);

            // ✅ تحقق من عدم وجود تسجيل مسبق في جدول user_enrolments
            if ($DB->record_exists('user_enrolments', [
                'enrolid' => $instance->id,
                'userid' => $userid
            ])) {
                echo "⏭ المستخدم مسجل مسبقًا في: {$course->fullname} <br>";
                continue;
            }

            try {
                $plugin->enrol_user($instance, $userid, $roleid, time());
                echo "✅ تم تسجيل المستخدم في: {$course->fullname} (ID: {$course->id})<br>";
                $count++;
            } catch (Exception $e) {
                echo "❌ فشل التسجيل في: {$course->fullname} — " . $e->getMessage() . "<br>";
            }

            break;
        }
    }
}

echo "<hr><strong>📌 المجموع: $count كورسات تم التسجيل فيها</strong>";
