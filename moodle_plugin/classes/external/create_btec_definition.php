<?php
/**
 * External API: Create BTEC Grading Definition
 *
 * Receives BTEC template data from Backend and creates grading definition in Moodle.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/lib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

/**
 * External API for creating BTEC grading definitions.
 */
class create_btec_definition extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Template name (unit name)'),
            'description' => new external_value(PARAM_RAW, 'Template description', VALUE_DEFAULT, ''),
            'zoho_unit_id' => new external_value(PARAM_TEXT, 'Zoho BTEC record ID'),
            'criteria' => new external_multiple_structure(
                new external_single_structure([
                    'shortname' => new external_value(PARAM_TEXT, 'Criterion code (e.g., P1, M1, D1)'),
                    'description' => new external_value(PARAM_RAW, 'Criterion description'),
                    'level' => new external_value(PARAM_TEXT, 'Level: pass, merit, or distinction'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0)
                ])
            )
        ]);
    }

    /**
     * Create BTEC grading definition.
     *
     * @param string $name Template name
     * @param string $description Template description
     * @param string $zoho_unit_id Zoho unit ID
     * @param array $criteria Array of criteria
     * @return array Result with success status
     */
    public static function execute($name, $description, $zoho_unit_id, $criteria) {
        global $DB, $USER;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'name' => $name,
            'description' => $description,
            'zoho_unit_id' => $zoho_unit_id,
            'criteria' => $criteria
        ]);

        // Check capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moodle_zoho_sync:manage', $context);

        try {
            // Create unique grading area for this unit (like old script)
            $areaname = 'btec_' . md5($params['name']);
            $existing_area = $DB->get_record('grading_areas', [
                'areaname' => $areaname,
                'activemethod' => 'btec'
            ]);
            
            if ($existing_area) {
                $areaid = (int)$existing_area->id;
            } else {
                // Try to link to course with same name (like old script)
                $contextid = 1; // System context default
                $course = $DB->get_record('course', ['fullname' => $params['name']]);
                if ($course) {
                    $context = \context_course::instance($course->id);
                    $contextid = $context->id;
                }
                
                $area = new \stdClass();
                $area->contextid = $contextid;
                $area->component = 'core_grading';
                $area->areaname = $areaname;
                $area->activemethod = 'btec';
                $areaid = (int)$DB->insert_record('grading_areas', $area);
            }
            
            // Check if definition already exists by name (like old script)
            $existing = $DB->get_record('grading_definitions', [
                'name' => $params['name'],
                'method' => 'btec'
            ]);
            
            if ($existing) {
                // Update existing definition
                $existing->areaid = $areaid;
                $existing->description = $params['description'];
                $existing->timemodified = time();
                $existing->usermodified = $USER->id;
                $DB->update_record('grading_definitions', $existing);
                
                $definition_id = $existing->id;
                $is_new = false;
                
                // Delete old criteria and recreate
                $DB->delete_records('gradingform_btec_criteria', ['definitionid' => $definition_id]);
            } else {
                // Create new grading definition
                $definition = new \stdClass();
                $definition->areaid = $areaid;
                $definition->method = 'btec';
                $definition->name = $params['name'];
                $definition->description = $params['description'];
                $definition->descriptionformat = FORMAT_HTML;
                $definition->status = 20; // DEFINITION_STATUS_READY
                $definition->timecreated = time();
                $definition->timemodified = time();
                $definition->usercreated = $USER->id;
                $definition->usermodified = $USER->id;

                $definition_id = $DB->insert_record('grading_definitions', $definition);
                $is_new = true;

                if (!$definition_id) {
                    throw new \moodle_exception('Failed to create grading definition');
                }
            }

            // Create criteria (for both new and updated definitions)
            $criteria_created = 0;
            foreach ($params['criteria'] as $criterion) {
                $record = new \stdClass();
                $record->definitionid = $definition_id;
                $record->shortname = $criterion['shortname'];
                $record->description = $criterion['description'];
                $record->descriptionformat = FORMAT_HTML;
                $record->sortorder = $criterion['sortorder'];

                $DB->insert_record('gradingform_btec_criteria', $record);
                $criteria_created++;
            }

            // Store or update Zoho mapping (for tracking)
            $dbman = $DB->get_manager();
            if ($dbman->table_exists(new \xmldb_table('local_mzi_btec_templates'))) {
                $existing_mapping = $DB->get_record('local_mzi_btec_templates', [
                    'zoho_unit_id' => $params['zoho_unit_id']
                ]);
                
                if ($existing_mapping) {
                    // Update existing mapping
                    $existing_mapping->definition_id = $definition_id;
                    $existing_mapping->unit_name = $params['name'];
                    $existing_mapping->synced_at = time();
                    $DB->update_record('local_mzi_btec_templates', $existing_mapping);
                } else {
                    // Insert new mapping
                    $mapping = new \stdClass();
                    $mapping->definition_id = $definition_id;
                    $mapping->zoho_unit_id = $params['zoho_unit_id'];
                    $mapping->unit_name = $params['name'];
                    $mapping->synced_at = time();
                    $DB->insert_record('local_mzi_btec_templates', $mapping);
                }
            }

            return [
                'success' => true,
                'message' => ($is_new ? "Created" : "Updated") . " definition '{$params['name']}' with {$criteria_created} criteria",
                'definition_id' => (int)$definition_id,
                'criteria_count' => $criteria_created
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'definition_id' => 0
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
            'definition_id' => new external_value(PARAM_INT, 'Created definition ID'),
            'criteria_count' => new external_value(PARAM_INT, 'Number of criteria created', VALUE_DEFAULT, 0)
        ]);
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    /**
     * Returns description of delete method parameters.
     */
    public static function delete_parameters() {
        return new external_function_parameters([
            'zoho_unit_id' => new external_value(PARAM_TEXT, 'Zoho BTEC unit record ID'),
        ]);
    }

    /**
     * Delete BTEC grading definition by Zoho unit ID.
     *
     * Removes:
     *  1. gradingform_btec_criteria rows for the definition
     *  2. grading_definitions row
     *  3. grading_areas row (only if no other definitions reference it)
     *  4. local_mzi_btec_templates mapping row
     *
     * @param string $zoho_unit_id  Zoho record ID stored in local_mzi_btec_templates
     * @return array Result with success status and message
     */
    public static function delete($zoho_unit_id) {
        global $DB;

        $params = self::validate_parameters(self::delete_parameters(), [
            'zoho_unit_id' => $zoho_unit_id,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moodle_zoho_sync:manage', $context);

        // 1. Check mapping table exists
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(new \xmldb_table('local_mzi_btec_templates'))) {
            return [
                'success'       => false,
                'message'       => 'Mapping table local_mzi_btec_templates does not exist',
                'definition_id' => 0,
            ];
        }

        // 2. Look up mapping
        $mapping = $DB->get_record('local_mzi_btec_templates', [
            'zoho_unit_id' => $params['zoho_unit_id'],
        ]);

        if (!$mapping) {
            return [
                'success'       => true,
                'message'       => "No mapping found for zoho_unit_id={$params['zoho_unit_id']} — nothing to delete",
                'definition_id' => 0,
            ];
        }

        $definition_id = (int) $mapping->definition_id;

        // 3. Verify definition still exists
        $definition = $DB->get_record('grading_definitions', ['id' => $definition_id]);
        if (!$definition) {
            $DB->delete_records('local_mzi_btec_templates', ['zoho_unit_id' => $params['zoho_unit_id']]);
            return [
                'success'       => true,
                'message'       => "Definition id={$definition_id} already absent — mapping cleaned up",
                'definition_id' => $definition_id,
            ];
        }

        $area_id = (int) $definition->areaid;

        // 4. Delete criteria
        $DB->delete_records('gradingform_btec_criteria', ['definitionid' => $definition_id]);

        // 5. Delete definition
        $DB->delete_records('grading_definitions', ['id' => $definition_id]);

        // 6. Delete area only if no other definitions reference it
        if ($DB->count_records('grading_definitions', ['areaid' => $area_id]) === 0) {
            $DB->delete_records('grading_areas', ['id' => $area_id]);
        }

        // 7. Remove mapping row
        $DB->delete_records('local_mzi_btec_templates', ['zoho_unit_id' => $params['zoho_unit_id']]);

        return [
            'success'       => true,
            'message'       => "Deleted definition id={$definition_id} (zoho_unit_id={$params['zoho_unit_id']})",
            'definition_id' => $definition_id,
        ];
    }

    /**
     * Returns description of delete method result value.
     */
    public static function delete_returns() {
        return new external_single_structure([
            'success'       => new external_value(PARAM_BOOL, 'Whether deletion succeeded'),
            'message'       => new external_value(PARAM_TEXT, 'Result message'),
            'definition_id' => new external_value(PARAM_INT, 'Deleted definition ID (0 if not found)'),
        ]);
    }
}
