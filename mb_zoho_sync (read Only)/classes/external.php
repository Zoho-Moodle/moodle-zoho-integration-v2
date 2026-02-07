<?php
namespace local_mb_zoho_sync;
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/externallib.php");

class external extends \external_api {
    public static function create_rubric_parameters() {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Moodle course ID'),
            'unitid'   => new \external_value(PARAM_TEXT, 'Zoho Unit record ID'),
        ]);
    }

    public static function create_rubric($courseid, $unitid) {
        global $DB;
        // هنا كود البناء نفسه: fetch Zoho unit, build $def, update_definition...
        return ['status'=>'ok'];
    }

    public static function create_rubric_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_TEXT, 'Status message')
        ]);
    }
}
