<?php
namespace local_mb_zoho_sync;

defined('MOODLE_INTERNAL') || die();

class observers {

    /**
     * 🟢 الحدث الأول: عند تقييم واجب (submission graded)
     */
    public static function submission_graded_handler(\mod_assign\event\submission_graded $event) {
        global $DB;

        $logfile = __DIR__ . '/../debug_log.txt';
        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 🔵 submission_graded_handler observer triggered\n", FILE_APPEND);

        // الحصول على بيانات التقييم
        $grade = $DB->get_record('assign_grades', ['id' => $event->objectid]);
        if (!$grade) return true;

        $assignment = $DB->get_record('assign', ['id' => $grade->assignment]);
        $course     = $DB->get_record('course', ['id' => $assignment->course]);
        $student    = $DB->get_record('user', ['id' => $grade->userid]);
        if (!$assignment || !$course || !$student) return true;

        // 💬 التعليق (Feedback)
        $feedback = '';
        $feedbackplugin = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
        if ($feedbackplugin && !empty($feedbackplugin->commenttext)) {
            $cleanhtml = format_text($feedbackplugin->commenttext, FORMAT_HTML);
            $feedback = trim(strip_tags($cleanhtml));
        }

        $studentid     = $student->id;
        $courseid      = $course->id;
        $attemptnumber = ($grade->attemptnumber ?? 0) + 1;
        $rawgrade      = $grade->grade;
        $attemptdate   = date('Y-m-d', $grade->timemodified);
        $moodlegradeid = $grade->id;
        $compositekey  = $studentid . '_' . $courseid;

        // 🎯 تحويل الدرجة إلى مستوى
        if (is_null($rawgrade)) {
            $finalgrade = "Refer";
        } elseif ($rawgrade >= 4) {
            $finalgrade = "Distinction";
        } elseif ($rawgrade >= 3) {
            $finalgrade = "Merit";
        } elseif ($rawgrade >= 2) {
            $finalgrade = "Pass";
        } else {
            $finalgrade = "Refer";
        }

        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] ℹ️ Final grade: $finalgrade, Record: {$student->firstname} {$student->lastname} - {$course->fullname}\n", FILE_APPEND);

        // 👩‍🏫 من هو المقيم (grader)
        $grader = $DB->get_record('user', ['id' => $event->userid]);
        $gradername = fullname($grader);
        $context = \context_course::instance($courseid);
        $roles = get_user_roles($context, $grader->id);
        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 🔍 Grader roles: " . json_encode($roles) . "\n", FILE_APPEND);

        $graderrole = '';
        foreach ($roles as $role) {
            if ($role->shortname === 'editingteacher') {
                $graderrole = 'teacher';
            } elseif ($role->shortname === 'internalverifier') {
                $graderrole = 'iv';
            }
        }

        // 🧩 حالة الـ workflow
        $gradestate = $DB->get_field('assign_user_flags', 'workflowstate', [
            'userid' => $studentid,
            'assignment' => $assignment->id
        ]) ?? 'Not marked';

        // 🔑 التوكن من Zoho
        file_get_contents('https://elearning.abchorizon.com/local/mb_zoho_sync/get_token.php');
        sleep(1);
        $tokenData = json_decode(file_get_contents(__DIR__ . '/../token.json'), true);
        $access_token = $tokenData['access_token'] ?? '';
        if (!$access_token) return true;

        // 🔍 جلب الـ Zoho IDs
        $studentZohoId = self::get_zoho_id('BTEC_Students', 'Student_Moodle_ID', $studentid, $access_token, $logfile);
        $classZohoId   = self::get_zoho_id('BTEC_Classes', 'Moodle_Class_ID', $courseid, $access_token, $logfile);
        if (!$studentZohoId || !$classZohoId) return true;

        // 🔎 التحقق من وجود سجل سابق
        $checkUrl = "https://www.zohoapis.com/crm/v2/BTEC_Grades/search?criteria=(Moodle_Grade_Composite_Key:equals:$compositekey)";
        $ch = curl_init($checkUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Zoho-oauthtoken $access_token"]
        ]);
        $checkResponse = curl_exec($ch);
        curl_close($ch);
        $checkData = json_decode($checkResponse, true);

        // 🧾 تحضير البيانات المرسلة إلى Zoho
        $recordData = [
            "Student"                    => ["id" => $studentZohoId],
            "Class"                      => ["id" => $classZohoId],
            "Grade"                      => $finalgrade,
            "Attempt_Number"             => (string)$attemptnumber,
            "Attempt_Date"               => $attemptdate,
            "Feedback"                   => $feedback,
            "Grade_Status"               => $gradestate,
            "Moodle_Grade_ID"            => (string)$moodlegradeid,
            "Moodle_Grade_Composite_Key" => $compositekey,
            "BTEC_Grade_Name"            => "{$student->firstname} {$student->lastname} - {$course->fullname} - $finalgrade - $attemptdate"
        ];

        if ($graderrole === 'teacher') {
            $recordData["Grader_Name"] = $gradername;
        } elseif ($graderrole === 'iv') {
            $recordData["IV_Name"] = $gradername;
        }

        $payload = ["data" => [$recordData]];

        // 🔁 تحديث أو إدخال سجل جديد
        if (isset($checkData['data'][0]['id'])) {
            $zohoid = $checkData['data'][0]['id'];
            $url = "https://www.zohoapis.com/crm/v2/BTEC_Grades/$zohoid";
            $method = 'PUT';
        } else {
            $url = "https://www.zohoapis.com/crm/v2/BTEC_Grades";
            $method = 'POST';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Zoho-oauthtoken $access_token",
                "Content-Type: application/json"
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 🔁 Zoho $method response: $response\n", FILE_APPEND);

        // 🧾 Log إضافي داخل grading_log.json
        $gradinglogfile = __DIR__ . '/../grading_log.json';
        if (!file_exists($gradinglogfile)) {
            file_put_contents($gradinglogfile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $grading_entry = [
            'timestamp'    => date('Y-m-d H:i:s'),
            'student_name' => fullname($student),
            'course_name'  => $course->fullname,
            'grade'        => $finalgrade,
            'status'       => ($method === 'PUT') ? 'Updated' : 'Created',
            'grader'       => $gradername,
            'grader_role'  => $graderrole,
            'action'       => $method
        ];

        $existing_entries = json_decode(file_get_contents($gradinglogfile), true);
        $existing_entries[] = $grading_entry;
        file_put_contents($gradinglogfile, json_encode($existing_entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return true;
    }

    /**
     * 👤 الحدث الثاني: عند إنشاء مستخدم جديد
     */
    public static function user_created_handler(\core\event\user_created $event) {
        global $DB;

        $logfile = __DIR__ . '/../debug_log.txt';
        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 👤 user_created_handler triggered\n", FILE_APPEND);

        $userid = $event->objectid;
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user || $user->deleted || $user->suspended) {
            file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] ⚠️ User not valid\n", FILE_APPEND);
            return true;
        }

        // 🔑 Zoho token
        file_get_contents('https://elearning.abchorizon.com/local/mb_zoho_sync/get_token.php');
        sleep(1);
        $tokenData = json_decode(file_get_contents(__DIR__ . '/../token.json'), true);
        $access_token = $tokenData['access_token'] ?? '';
        if (!$access_token) return true;

        // 🔁 تحديث الـ Moodle ID في Zoho
        $zohoId = self::find_and_update_user('BTEC_Students', 'Academic_Email', $user->username, 'Student_Moodle_ID', $userid, $access_token, $logfile);
        if (!$zohoId) {
            $zohoId = self::find_and_update_user('BTEC_Teachers', 'Academic_Email', $user->username, 'Teacher_Moodle_ID', $userid, $access_token, $logfile);
        }

        return true;
    }

    /**
     * 🔍 دالة مساعدة للبحث عن سجل Zoho
     */
    private static function get_zoho_id($module, $field, $value, $token, $logfile) {
        $url = "https://www.zohoapis.com/crm/v2/$module/search?criteria=($field:equals:$value)";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Zoho-oauthtoken $token"]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 🔍 $module Search Response: $response\n", FILE_APPEND);
        $data = json_decode($response, true);
        return $data['data'][0]['id'] ?? null;
    }

    /**
     * 🔁 دالة مساعدة للبحث وتحديث مستخدم في Zoho
     */
    private static function find_and_update_user($module, $searchfield, $searchvalue, $updatefield, $userid, $token, $logfile) {
        $url = "https://www.zohoapis.com/crm/v2/$module/search?criteria=($searchfield:equals:$searchvalue)";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Zoho-oauthtoken $token"]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 🔍 $module search response: $response\n", FILE_APPEND);
        $data = json_decode($response, true);
        if (!isset($data['data'][0]['id'])) return null;

        $zohoid = $data['data'][0]['id'];
        $payload = json_encode(['data' => [[ $updatefield => (string)$userid ]]]);
        $updateurl = "https://www.zohoapis.com/crm/v2/$module/$zohoid";

        $ch = curl_init($updateurl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Zoho-oauthtoken $token",
                "Content-Type: application/json"
            ]
        ]);
        $updateresponse = curl_exec($ch);
        curl_close($ch);

        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 🔁 $module update response: $updateresponse\n", FILE_APPEND);
        return $zohoid;
    }
}
