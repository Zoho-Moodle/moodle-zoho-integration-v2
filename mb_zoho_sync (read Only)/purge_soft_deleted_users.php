#!/usr/bin/env php
<?php
// Purge users with deleted=1 using Moodle's delete_user()
// Usage examples:
//   php purge_soft_deleted_users.php --dry-run
//   php purge_soft_deleted_users.php --limit=200 --older-than-days=30
//   php purge_soft_deleted_users.php --ids=101,202,303 --yes

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

// Moodle libs
require_once($CFG->libdir . '/moodlelib.php'); // delete_user()
require_once($CFG->libdir . '/clilib.php');    // cli_* helpers

// ---- Parse options ----
list($options, $unrecognized) = cli_get_params(
    [
        'dry-run'          => false,
        'limit'            => 0,        // 0 = no limit
        'older-than-days'  => 0,        // 0 = no age filter
        'ids'              => '',       // comma-separated list of user IDs
        'yes'              => false,    // skip confirmation
        'help'             => false,
    ],
    [
        'h' => 'help'
    ]
);

if (!empty($unrecognized)) {
    $unrec = implode(', ', $unrecognized);
    cli_error("Unrecognized options: {$unrec}");
}

if (!empty($options['help'])) {
    $help = <<<EOT
Purge (permanently delete) Moodle users where deleted=1.

Options:
  --dry-run               Show what WOULD be deleted, but do nothing.
  --limit=NUM             Max users to process (default: no limit).
  --older-than-days=NUM   Only process users whose timecreated is older than NUM days.
  --ids=ID1,ID2,...       Only process these specific user IDs (overrides other filters).
  --yes                   Do not ask for confirmation.
  -h, --help              Show this help.

Examples:
  php purge_soft_deleted_users.php --dry-run
  php purge_soft_deleted_users.php --limit=200 --older-than-days=30
  php purge_soft_deleted_users.php --ids=101,202,303 --yes

EOT;
    echo $help . PHP_EOL;
    exit(0);
}

global $DB;

// ---- Build selection ----
$params = [];
$where  = 'deleted = 1';

if (!empty($options['ids'])) {
    $idlist = array_filter(array_map('trim', explode(',', $options['ids'])), 'strlen');
    $idlist = array_map('intval', $idlist);
    if (empty($idlist)) {
        cli_error('No valid IDs provided in --ids.');
    }
    list($insql, $inparams) = $DB->get_in_or_equal($idlist, SQL_PARAMS_NAMED);
    $where = "id $insql";
    $params = $inparams;
} else {
    $olderdays = (int)$options['older-than-days'];
    if ($olderdays > 0) {
        $threshold = time() - ($olderdays * 86400);
        $where .= ' AND timecreated < :threshold';
        $params['threshold'] = $threshold;
    }
}

$limit = (int)$options['limit'];
$dry   = !empty($options['dry-run']);

// ---- Fetch candidates ----
$fields = 'id, username, email, firstname, lastname, timecreated';
$candidates = $DB->get_records_select('user', $where, $params, 'id ASC', $fields, 0, $limit ?: 0);
$total = count($candidates);

cli_writeln("🔎 Candidates found: {$total}");
if ($total === 0) {
    exit(0);
}

// ---- Safety confirmation ----
if (!$dry && empty($options['yes'])) {
    cli_writeln("⚠️  This will PERMANENTLY delete {$total} user(s). Type 'PURGE' to continue:");
    $input = trim(fgets(STDIN));
    if ($input !== 'PURGE') {
        cli_writeln('Aborted.');
        exit(1);
    }
}

// ---- Log file (JSON lines) ----
$logfile = $CFG->dataroot . '/purge_deleted_users.log.jsonl';
$logfh = fopen($logfile, 'ab');
if (!$logfh) {
    cli_writeln("⚠️ Could not open log file: {$logfile} (continuing without file logging)");
}

// ---- Process ----
$ok = 0; $fail = 0;
foreach ($candidates as $u) {
    $info = [
        'ts'        => date('c'),
        'id'        => (int)$u->id,
        'username'  => (string)$u->username,
        'email'     => (string)$u->email,
        'firstname' => (string)$u->firstname,
        'lastname'  => (string)$u->lastname,
        'timecreated' => (int)$u->timecreated,
        'action'    => $dry ? 'dry-run' : 'purge',
        'status'    => null,
        'message'   => null,
    ];

    // Extra guard: never touch guest/admin even if someone set them deleted=1 (paranoid)
    if (in_array($u->username, ['guest', 'admin'], true)) {
        $info['status']  = 'skipped_protected';
        $info['message'] = 'Protected username';
        cli_writeln("⏭️  Skipped protected user #{$u->id} ({$u->username})");
        if ($logfh) fwrite($logfh, json_encode($info, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        continue;
    }

    if ($dry) {
        $info['status'] = 'would_delete';
        cli_writeln("📝 [DRY] Would delete #{$u->id} {$u->username} <{$u->email}>");
        if ($logfh) fwrite($logfh, json_encode($info, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        continue;
    }

    // Load full record (defensive)
    $user = $DB->get_record('user', ['id' => $u->id], '*', IGNORE_MISSING);
    if (!$user) {
        $info['status']  = 'not_found';
        $info['message'] = 'User record missing at deletion time';
        $fail++;
        cli_writeln("❌ Not found at deletion time: #{$u->id}");
        if ($logfh) fwrite($logfh, json_encode($info, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        continue;
    }

    try {
        $result = delete_user($user); // Moodle core API: fully deletes user and associated data
        if ($result) {
            $info['status'] = 'deleted';
            $ok++;
            cli_writeln("✅ Deleted #{$u->id} {$u->username}");
        } else {
            $info['status']  = 'failed';
            $info['message'] = 'delete_user() returned false';
            $fail++;
            cli_writeln("⚠️ Failed (delete_user=false) #{$u->id} {$u->username}");
        }
    } catch (Throwable $e) {
        $info['status']  = 'error';
        $info['message'] = $e->getMessage();
        $fail++;
        cli_writeln("💥 Error deleting #{$u->id}: " . $e->getMessage());
    }

    if ($logfh) fwrite($logfh, json_encode($info, JSON_UNESCAPED_UNICODE) . PHP_EOL);
}

if ($logfh) fclose($logfh);

cli_writeln("——— Summary ———");
cli_writeln("Processed: {$total}");
cli_writeln("Deleted:   {$ok}");
cli_writeln("Failed:    {$fail}");
cli_writeln("Log file:  {$logfile}");
