<?php
/**
 * External API: Delete BTEC Grading Definition
 *
 * Deletes a Moodle grading definition that was previously created by
 * local_mzi_create_btec_definition, identified by zoho_unit_id.
 *
 * Removes:
 *  1. gradingform_btec_criteria rows for the definition
 *  2. grading_definitions row
 *  3. grading_areas row (only if no other definitions reference it)
 *  4. local_mzi_btec_templates mapping row
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_system;

/**
 * External API for deleting BTEC grading definitions.
 */
class delete_btec_definition extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'zoho_unit_id' => new external_value(PARAM_TEXT, 'Zoho BTEC unit record ID'),
        ]);
    }

    /**
     * Delete BTEC grading definition by Zoho unit ID.
     *
     * @param string $zoho_unit_id  Zoho record ID from local_mzi_btec_templates
     * @return array Result with success status and message
     */
    public static function execute($zoho_unit_id) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'zoho_unit_id' => $zoho_unit_id,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moodle_zoho_sync:manage', $context);

        // ── 1. Look up mapping ──────────────────────────────────────────────
        $dbman = $DB->get_manager();
        $table_exists = $dbman->table_exists(new \xmldb_table('local_mzi_btec_templates'));

        if (!$table_exists) {
            return [
                'success' => false,
                'message' => 'Mapping table local_mzi_btec_templates does not exist',
                'definition_id' => 0,
            ];
        }

        $mapping = $DB->get_record('local_mzi_btec_templates', [
            'zoho_unit_id' => $params['zoho_unit_id'],
        ]);

        if (!$mapping) {
            return [
                'success' => true,
                'message' => "No mapping found for zoho_unit_id={$params['zoho_unit_id']} — nothing to delete",
                'definition_id' => 0,
            ];
        }

        $definition_id = (int) $mapping->definition_id;

        // ── 2. Verify definition still exists ───────────────────────────────
        $definition = $DB->get_record('grading_definitions', ['id' => $definition_id]);

        if (!$definition) {
            // Mapping exists but definition was already removed — clean up mapping
            $DB->delete_records('local_mzi_btec_templates', ['zoho_unit_id' => $params['zoho_unit_id']]);
            return [
                'success' => true,
                'message' => "Definition id={$definition_id} already absent — mapping cleaned up",
                'definition_id' => $definition_id,
            ];
        }

        $area_id = (int) $definition->areaid;

        // ── 3. Delete criteria ───────────────────────────────────────────────
        $DB->delete_records('gradingform_btec_criteria', ['definitionid' => $definition_id]);

        // ── 4. Delete definition ─────────────────────────────────────────────
        $DB->delete_records('grading_definitions', ['id' => $definition_id]);

        // ── 5. Delete grading area if no other definitions reference it ──────
        $other_definitions = $DB->count_records('grading_definitions', ['areaid' => $area_id]);
        if ($other_definitions === 0) {
            $DB->delete_records('grading_areas', ['id' => $area_id]);
        }

        // ── 6. Remove mapping row ────────────────────────────────────────────
        $DB->delete_records('local_mzi_btec_templates', ['zoho_unit_id' => $params['zoho_unit_id']]);

        return [
            'success'       => true,
            'message'       => "Deleted definition id={$definition_id} (zoho_unit_id={$params['zoho_unit_id']})",
            'definition_id' => $definition_id,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success'       => new external_value(PARAM_BOOL, 'Whether deletion succeeded'),
            'message'       => new external_value(PARAM_TEXT, 'Result message'),
            'definition_id' => new external_value(PARAM_INT, 'Deleted definition ID (0 if not found)'),
        ]);
    }
}
