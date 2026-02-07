<?php
/**
 * Fix events stuck in 'retrying' status
 * Run once from command line: php fix_retrying_events.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

global $DB;

$sql = "UPDATE {local_mzi_event_log} 
        SET status = 'failed' 
        WHERE status = 'retrying'";

$affected = $DB->execute($sql);

echo "Updated retrying events to failed status\n";
echo "You can now retry them from the Event Logs page\n";
