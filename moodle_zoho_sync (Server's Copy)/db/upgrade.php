<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database upgrade script for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade from the given old version.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool Success
 */
function xmldb_local_moodle_zoho_sync_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Placeholder for future upgrades.
    if ($oldversion < 2026020100) {
        // Initial release - no upgrades needed yet.
        upgrade_plugin_savepoint(true, 2026020100, 'local', 'moodle_zoho_sync');
    }

    // Version 2026020101: Rename tables from mb_zoho_* to local_mzi_*.
    // Also rename timestamp fields to Moodle standard names.
    if ($oldversion < 2026020101) {

        // Rename tables if they exist with old names.
        
        // 1. Event log table.
        if ($dbman->table_exists('mb_zoho_event_log')) {
            $table = new xmldb_table('mb_zoho_event_log');
            $dbman->rename_table($table, 'local_mzi_event_log');
        }
        
        // 2. Sync history table.
        if ($dbman->table_exists('mb_zoho_sync_history')) {
            $table = new xmldb_table('mb_zoho_sync_history');
            $dbman->rename_table($table, 'local_mzi_sync_history');
        }
        
        // 3. Config table.
        if ($dbman->table_exists('mb_zoho_config')) {
            $table = new xmldb_table('mb_zoho_config');
            $dbman->rename_table($table, 'local_mzi_config');
        }
        
        // Rename timestamp fields in event_log table.
        $table = new xmldb_table('local_mzi_event_log');
        
        if ($dbman->field_exists($table, new xmldb_field('created_at'))) {
            $field = new xmldb_field('created_at');
            $dbman->rename_field($table, $field, 'timecreated');
        }
        
        if ($dbman->field_exists($table, new xmldb_field('updated_at'))) {
            $field = new xmldb_field('updated_at');
            $dbman->rename_field($table, $field, 'timemodified');
        }
        
        if ($dbman->field_exists($table, new xmldb_field('processed_at'))) {
            $field = new xmldb_field('processed_at');
            $dbman->rename_field($table, $field, 'timeprocessed');
        }
        
        // Rename index from created_at_idx to timecreated_idx.
        $index = new xmldb_index('created_at_idx', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        $newindex = new xmldb_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        
        // Rename timestamp fields in sync_history table.
        $table = new xmldb_table('local_mzi_sync_history');
        
        if ($dbman->field_exists($table, new xmldb_field('started_at'))) {
            $field = new xmldb_field('started_at');
            $dbman->rename_field($table, $field, 'timestarted');
        }
        
        if ($dbman->field_exists($table, new xmldb_field('completed_at'))) {
            $field = new xmldb_field('completed_at');
            $dbman->rename_field($table, $field, 'timecompleted');
        }
        
        // Rename index.
        $index = new xmldb_index('started_at_idx', XMLDB_INDEX_NOTUNIQUE, array('timestarted'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        $newindex = new xmldb_index('timestarted_idx', XMLDB_INDEX_NOTUNIQUE, array('timestarted'));
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        
        // Rename timestamp field in config table.
        $table = new xmldb_table('local_mzi_config');
        
        if ($dbman->field_exists($table, new xmldb_field('updated_at'))) {
            $field = new xmldb_field('updated_at');
            $dbman->rename_field($table, $field, 'timemodified');
        }

        upgrade_plugin_savepoint(true, 2026020101, 'local', 'moodle_zoho_sync');
    }
    
    // Version 2026020102: Add next_retry_at field for exponential backoff retry system.
    if ($oldversion < 2026020102) {
        $table = new xmldb_table('local_mzi_event_log');
        
        // Add next_retry_at field for production-grade retry scheduling.
        $field = new xmldb_field('next_retry_at', XMLDB_TYPE_INTEGER, '10', null, false, null, null, 'retry_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add index for efficient retry task query.
        $index = new xmldb_index('next_retry_at_idx', XMLDB_INDEX_NOTUNIQUE, array('next_retry_at'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_plugin_savepoint(true, 2026020102, 'local', 'moodle_zoho_sync');
    }

    return true;
}
