<?php
require('../../config.php');
require_login();

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

echo "<h2>🟢 Starting Microsoft Teams SharePoint link retrieval...</h2>";

global $DB, $CFG;

/* ===================== PRUNE ORPHANS (Hard Delete) ===================== */
/**
 * يحذف من sync_sharepoint أي صف:
 *  - كورسو غير موجود في {course}
 *  - أو ما له ربط Teams فعّال في {local_o365_objects} (type=group, subtype=course)
 */
function prune_sync_sharepoint_orphans(): int {
    global $DB;
    $sql = "
        SELECT ss.id
        FROM {sync_sharepoint} ss
        LEFT JOIN {local_o365_objects} lo
               ON lo.moodleid = ss.courseid
              AND lo.type = 'group' AND lo.subtype = 'course'
        LEFT JOIN {course} c
               ON c.id = ss.courseid
        WHERE lo.id IS NULL OR c.id IS NULL
    ";
    $ids = $DB->get_fieldset_sql($sql);
    if (!empty($ids)) {
        $DB->delete_records_list('sync_sharepoint', 'id', $ids);
        return count($ids);
    }
    return 0;
}

$pruned = prune_sync_sharepoint_orphans();
echo "<p>🧹 Pruned orphan rows from sync_sharepoint: <b>{$pruned}</b></p>";

/* ===================== TOKEN ===================== */
echo "<p>Fetching latest token...</p>";
$tokenPath = __DIR__ . '/microsoft_token.json';
$tokenResponse = @file_get_contents($CFG->wwwroot . '/local/mb_zoho_sync/get_microsoft_token.php');
if ($tokenResponse === false) {
    echo "<p style='color:red;'>❌ Failed to call get_microsoft_token.php</p>";
} else {
    echo "<p>✅ Token request executed.</p>";
}
usleep(300000); // 0.3s

if (!file_exists($tokenPath)) {
    echo "<p style='color:red;'>❌ Token file not found at ".htmlspecialchars($tokenPath, ENT_QUOTES)."</p>";
    exit;
}

$tokenData    = json_decode(file_get_contents($tokenPath), true);
$access_token = $tokenData['access_token'] ?? '';

if (!$access_token) {
    echo "<p style='color:red;'>❌ Access token missing in token file</p>";
    exit;
} else {
    echo "<p>✅ Access token loaded successfully.</p>";
}

/* ===================== DATASET ===================== */
/**
 * نشتغل فقط على الكورسات الموجودة فعليًا + المرتبطة بتيمز.
 */
$sql = "SELECT
            lo.objectid       AS objectid,
            lo.moodleid       AS moodleid,
            lo.o365name       AS o365name,
            ss.sharepointlink AS sharepointlink
        FROM {local_o365_objects} lo
        JOIN {course} c
          ON c.id = lo.moodleid
        LEFT JOIN {sync_sharepoint} ss
          ON ss.courseid = lo.moodleid
        WHERE lo.type = 'group' AND lo.subtype = 'course'
        ORDER BY lo.moodleid ASC";

$records = $DB->get_records_sql($sql);

if (empty($records)) {
    echo "<p style='color:red;'>❌ No active course groups found.</p>";
    exit;
} else {
    echo "<p>✅ Found " . count($records) . " active course groups.</p>";
}

$totalCourses     = count($records);
$completedCourses = 0;

ob_start();
?>

<style>
.progress-container { width: 95%; max-width: 800px; background-color: #ddd; border-radius: 10px; overflow: hidden; margin: 20px auto; height: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
.progress-bar { height: 100%; width: 0; background-color: #4CAF50; text-align: center; line-height: 30px; color: white; font-weight: bold; transition: width 0.4s ease;}
.teams-table { border-collapse: collapse; margin: 20px auto; width: 95%; max-width: 1100px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); font-family: 'Segoe UI', sans-serif;}
.teams-table th { background-color: #0073aa; color: white; padding: 12px 16px; font-size: 16px; text-align: center;}
.teams-table td { background-color: #f9f9f9; padding: 12px 14px; text-align: center; font-size: 15px; border-bottom: 1px solid #e0e0e0;}
.teams-table tr:hover td { background-color: #f1f1f1;}
.teams-table a { color: #0073aa; text-decoration: none;}
.teams-table a:hover { text-decoration: underline;}
.team-icon { margin-right: 6px; color: #0073aa;}
</style>

<div class="progress-container">
    <div id="progress-bar" class="progress-bar">0%</div>
</div>

<table class="teams-table">
<tr><th>Course ID</th><th>Team Name</th><th>Object ID</th><th>Status / Info</th><th>SharePoint Link</th></tr>

<?php
foreach ($records as $record) {
    $objectid = $record->objectid;
    $courseid = $record->moodleid;
    $teamname = $record->o365name ?? '-';
    $link     = '-';
    $status   = "❌ Not Retrieved";

    echo "<tr>";
    echo "<td>" . htmlspecialchars($courseid) . "</td>";
    echo "<td><span class='team-icon'>👥</span>" . htmlspecialchars($teamname) . "</td>";
    echo "<td>" . htmlspecialchars($objectid) . "</td>";

    try {
        // 0) تأكيد سريع أن الـ groupId موجود ومعقول
        if (!group_exists_and_matches($objectid, $teamname, $access_token)) {
            $status = "❌ Invalid or mismatched groupId for this team";
        } else {
            // 1) الطريقة الأضمن: channel Files → driveId → root
            $alt = resolve_docs_via_primary_channel($objectid, $access_token);
            if ($alt) {
                $link   = $alt;
                $status = "✅ Link Retrieved (via primaryChannel → drive root)";
            } else {
                // 2) fallback دقيق: site → drives(documentLibrary) → root
                $alt2 = resolve_docs_via_site($objectid, $access_token);
                if ($alt2) {
                    $link   = $alt2;
                    $status = "✅ Link Retrieved (via site→drives→root)";
                } else {
                    // 3) آخر محاولة: groups/{id}/drive/root (أقل ثباتًا)
                    $graphUrl = "https://graph.microsoft.com/v1.0/groups/$objectid/drive/root";
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $graphUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => ["Authorization: Bearer $access_token","Content-Type: application/json"],
                        CURLOPT_CONNECTTIMEOUT => 6,
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    $response  = curl_exec($ch);
                    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($httpCode >= 200 && $httpCode < 300) {
                        $responseData = json_decode($response, true);
                        if (!empty($responseData['webUrl'])) {
                            $link   = $responseData['webUrl'];
                            $status = "✅ Link Retrieved (groups/drive/root)";
                        } else {
                            $status = "❌ webUrl missing (groups/drive/root)";
                        }
                    } else {
                        $status = "❌ HTTP $httpCode | Curl: $curlError | Resp: " . htmlspecialchars($response);
                    }
                }
            }
        }

        // تحديث/إدراج في sync_sharepoint
        $data = (object)[
            'courseid'       => $courseid,
            'teamname'       => $teamname,
            'objectid'       => $objectid,
            'status'         => ($link === '-' ? 'resolve_failed' : 'ok'),
            'sharepointlink' => ($link === '-' ? '' : $link),
            'timecreated'    => time(),
        ];
        if ($exist = $DB->get_record('sync_sharepoint', ['courseid' => $courseid])) {
            $data->id = $exist->id;
            $DB->update_record('sync_sharepoint', $data);
            if ($link !== '-') { $status .= " | DB updated"; }
        } else {
            $DB->insert_record('sync_sharepoint', $data);
            if ($link !== '-') { $status .= " | DB inserted"; }
        }

    } catch (Exception $e) {
        $status = "❌ Exception: " . $e->getMessage();
        $link   = '-';
    }

    echo "<td>$status</td>";
    echo "<td>";
    if (!empty($link) && $link !== '-') {
        echo "<a href='" . htmlspecialchars($link, ENT_QUOTES) . "' target='_blank'>Open Link</a>";
    } else {
        echo "-";
    }
    echo "</td>";
    echo "</tr>";

    $completedCourses++;
    $percent = intval(($completedCourses / $totalCourses) * 100);
    echo "<script>
        document.getElementById('progress-bar').style.width = '{$percent}%';
        document.getElementById('progress-bar').innerText = '{$percent}%';
    </script>";
    flush();
    ob_flush();
}
?>
</table>

<?php
/* ===================== Helper Functions ===================== */

function gget_graph(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", "Accept: application/json"],
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['http'=>$code, 'body'=>$body?:'', 'err'=>$err];
}

/** تأكيد سريع أن الـ groupId موجود (ويمسك GUID الغلط) */
function group_exists_and_matches(string $groupid, string $expectedName, string $token): bool {
    $g = gget_graph("https://graph.microsoft.com/v1.0/groups/{$groupid}?\$select=id,displayName,resourceProvisioningOptions", $token);
    if ($g['http'] !== 200) return false;
    $j = json_decode($g['body'], true);
    if (empty($j['id'])) return false;
    $opts = array_map('strtolower', $j['resourceProvisioningOptions'] ?? []);
    // يكفي أنه group موجود؛ المطابقة الحرفية للاسم غير مطلوبة
    return true; // أو: return in_array('team', $opts, true);
}

/** المسار الأضمن: Team → Primary Channel → filesFolder → driveId → root.webUrl */
function resolve_docs_via_primary_channel(string $groupid, string $token): ?string {
    $ff = gget_graph("https://graph.microsoft.com/v1.0/teams/{$groupid}/primaryChannel/filesFolder?\$select=webUrl,parentReference", $token);
    if ($ff['http'] !== 200) return null;
    $fj = json_decode($ff['body'], true);
    $driveId = $fj['parentReference']['driveId'] ?? null;
    if (!$driveId) return null;

    $root = gget_graph("https://graph.microsoft.com/v1.0/drives/{$driveId}/root?\$select=webUrl", $token);
    if ($root['http'] !== 200) return null;
    $rj = json_decode($root['body'], true);
    return $rj['webUrl'] ?? null;
}

/** fallback: group → siteId → drives(documentLibrary) → أفضل Drive → root.webUrl */
function resolve_docs_via_site(string $groupid, string $token): ?string {
    $s = gget_graph("https://graph.microsoft.com/v1.0/groups/{$groupid}/sites/root?\$select=id", $token);
    if ($s['http'] !== 200) return null;
    $siteId = json_decode($s['body'], true)['id'] ?? null;
    if (!$siteId) return null;

    $d = gget_graph("https://graph.microsoft.com/v1.0/sites/{$siteId}/drives?\$select=id,name,driveType", $token);
    if ($d['http'] !== 200) return null;
    $list = json_decode($d['body'], true)['value'] ?? [];
    $docs = array_values(array_filter($list, fn($x) => ($x['driveType'] ?? '') === 'documentLibrary'));
    if (!$docs) return null;

    usort($docs, function($a, $b){ return rank_doclib_name($a['name'] ?? '') <=> rank_doclib_name($b['name'] ?? ''); });
    $driveId = $docs[0]['id'] ?? null;
    if (!$driveId) return null;

    $root = gget_graph("https://graph.microsoft.com/v1.0/drives/{$driveId}/root?\$select=webUrl", $token);
    if ($root['http'] !== 200) return null;
    return json_decode($root['body'], true)['webUrl'] ?? null;
}

/** ترتيب تفضيلي لأسماء المكتبات */
function rank_doclib_name(string $name): int {
    $n = mb_strtolower($name, 'UTF-8');
    if ($n === 'documents') return 1;
    if ($n === 'shared documents') return 2;
    if (str_starts_with($n, 'documents')) return 3;
    if (str_starts_with($n, 'shared')) return 4;
    return 9;
}

ob_end_flush();
