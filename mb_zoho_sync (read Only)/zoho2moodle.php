<?php
require_once(__DIR__ . '/../../config.php');

// ✅ تنفيذ الرابط الخارجي لإنشاء التوكن وتخزينه في token.json
file_get_contents('https://elearning.abchorizon.com/local/mb_zoho_sync/get_token.php');

// ✅ انتظار ثانية للتأكد من كتابة التوكن
sleep(1);

// ✅ قراءة التوكن من ملف token.json
$tokenData = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
$access_token = $tokenData['access_token'];

$zoho_api_url = 'https://www.zohoapis.com/crm/v2/BTEC_Students';

// ✅ تأكد من وجود جدول سجل التزامن
$check = $DB->get_manager()->table_exists('financeinfo_sync_log');
if (!$check) {
    $dbman = $DB->get_manager();
    $table = new xmldb_table('financeinfo_sync_log');
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', true, true, true);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', true);
    $table->add_field('last_zoho_hash', XMLDB_TYPE_CHAR, '255', null, false);
    $table->add_field('last_synced_at', XMLDB_TYPE_INTEGER, '10');
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $dbman->create_table($table);
    echo "✅ Created sync log table.<br>";
}

// ✅ دالة لإعادة الاتصال في حال فقد الاتصال
function db_reconnect_safe() {
    global $CFG, $DB;
    try {
        require_once($CFG->dirroot . '/lib/dml/mysqli_native_moodle_database.php');
        $newdb = new mysqli_native_moodle_database();
        $newdb->connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname, $CFG->prefix, $CFG->dboptions);
        $GLOBALS['DB'] = $newdb;
        echo "🔄 Reconnected to the database.<br>";
    } catch (Exception $e) {
        echo "❌ Failed to reconnect to the database: " . $e->getMessage() . "<br>";
        exit;
    }
}

// ✅ دالة للتعامل الآمن مع جلب المستخدم
function safe_get_user_by_username($username) {
    global $DB;
    try {
        return $DB->get_record('user', ['username' => $username]);
    } catch (dml_read_exception $e) {
        if (strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
            echo "🛠 Lost DB connection. Reconnecting...<br>";
            db_reconnect_safe();
            return $DB->get_record('user', ['username' => $username]);
        } else {
            echo "❌ Unexpected DB error: " . $e->getMessage() . "<br>";
            return false;
        }
    }
}

function fetch_students_from_zoho($access_token, $maxmatches = 0) {
    $matched = [];
    $page = 1;
    $limit = 100;

    do {
        $url = "https://www.zohoapis.com/crm/v2/BTEC_Students?page=$page&per_page=$limit";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Zoho-oauthtoken $access_token"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if (!isset($data['data'])) break;

        global $DB;
        foreach ($data['data'] as $student) {
            $username = strtolower(trim($student['Academic_Email'] ?? ''));
            echo "🔍 Matching with username: $username<br>";
            if (empty($username)) continue;

            $user = safe_get_user_by_username($username);
            if (!$user) {
                echo "❌ Moodle user not found. Skipping.<br><br>";
                continue;
            }

            $zoho_data_hash = md5(json_encode($student));
            $log = $DB->get_record('financeinfo_sync_log', ['userid' => $user->id]);
            if ($log && $log->last_zoho_hash === $zoho_data_hash) {
                echo "⏩ No change in data. Skipping.<br><br>";
                continue;
            }

            echo "✅ Found in Moodle: " . fullname($user) . "<br><br>";
            $matched[] = [$student, $user, $zoho_data_hash];

            // ✅ لا توقف التكرار إذا كان $maxmatches = 0
            if ($maxmatches > 0 && count($matched) >= $maxmatches) break;
        }

        $more = isset($data['info']['more_records']) && $data['info']['more_records'];
        $page++;
    } while ($more && ($maxmatches == 0 || count($matched) < $maxmatches));

    return $matched;
}

function sync_financeinfo($student, $user, $zoho_data_hash, $DB) {
    $record = new stdClass();
    $record->userid = $user->id;

    $map = [
        'Scholarship' => 'scholarship',
        'Reason_of_scholarship' => 'scholarship_reason',
        'Scholarship_Percentage' => 'scholarship_percentage',
        'Currency_of_transferred_amount' => 'currency',
        'Amount_Transferred' => 'amount_transferred',
        'Payment_Method' => 'payment_method',
        'Payment_Mode' => 'payment_mode',
        'Bank_Name' => 'bank_name',
        'Bank_Holder_Name' => 'bank_holder',
        'Registration_Fees' => 'registration_fees',
        'Invoice_Reg_Fees' => 'invoice_reg_fees',
        'Total_Amount' => 'total_amount',
        'Discount_Amount' => 'discount_amount'
    ];

    foreach ($map as $zohoField => $dbField) {
        if (isset($student[$zohoField]) && $student[$zohoField] !== '') {
            $record->$dbField = (string) $student[$zohoField];
        }
    }
    $record->zoho_id = $student['id'];

    if ($DB->record_exists('financeinfo', ['userid' => $user->id])) {
        $existing = $DB->get_record('financeinfo', ['userid' => $user->id]);
        $record->id = $existing->id;
        $DB->update_record('financeinfo', $record);
        $financeinfoid = $existing->id;
        echo "🔁 Updated finance info.<br>";
    } else {
        $financeinfoid = $DB->insert_record('financeinfo', $record);
        echo "➕ Inserted finance info.<br>";
    }

    $DB->delete_records('financeinfo_payments', ['financeinfoid' => $financeinfoid]);
    $payments = [
        ['st_Payment', 'st_Payment_Date', 'Invoice_Num_1'],
        ['nd_Payment', 'nd_Payment_Date', 'Invoice_Num_2'],
        ['rd_Payment', 'rd_Payment_Date', 'Invoice_Num_3'],
        ['th_Payment', 'th_Payment_Date', 'Invoice_Num_4'],
        ['th_Payment1', 'th_Payment_Date1', 'Invoice_Num_5'],
        ['th_Payment2', 'th_Payment_Date2', 'Invoice_Num_6'],
        ['th_Payment3', 'th_Payment_Date3', 'Invoice_Num_7'],
        ['th_Payment4', 'th_Payment_Date4', 'Invoice_Num_8']
    ];

    $count = 1;
    foreach ($payments as [$amountField, $dateField, $invoiceField]) {
        if (!empty($student[$amountField]) && !empty($student[$dateField])) {
            $payment = new stdClass();
            $payment->financeinfoid = $financeinfoid;
            $payment->payment_name = "Payment $count";
            $payment->amount = (float) $student[$amountField];
            $payment->payment_date = strtotime($student[$dateField]);
            $payment->invoice_number = (string) ($student[$invoiceField] ?? '');
            $DB->insert_record('financeinfo_payments', $payment);
            echo "💰 Inserted Payment $count<br>";
        }
        $count++;
    }

    $log = $DB->get_record('financeinfo_sync_log', ['userid' => $user->id]);
    if ($log) {
        $log->last_zoho_hash = $zoho_data_hash;
        $log->last_synced_at = time();
        $DB->update_record('financeinfo_sync_log', $log);
    } else {
        $log = new stdClass();
        $log->userid = $user->id;
        $log->last_zoho_hash = $zoho_data_hash;
        $log->last_synced_at = time();
        $DB->insert_record('financeinfo_sync_log', $log);
    }

    echo "✅ Sync complete for " . fullname($user) . "<hr>";
}

// ✅ التنفيذ الرئيسي - دون حد أقصى للطلاب
$matched_students = fetch_students_from_zoho($access_token, 0);
if (empty($matched_students)) {
    echo "❌ No new or changed students to sync.";
} else {
    foreach ($matched_students as [$student, $user, $hash]) {
        sync_financeinfo($student, $user, $hash, $DB);
    }
}
?>
