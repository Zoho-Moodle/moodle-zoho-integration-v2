<?php
/**
 * Custom admin setting for encrypted token storage
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/config_manager.php');

use local_moodle_zoho_sync\config_manager;

/**
 * Admin setting for encrypted API token.
 * 
 * SECURITY MODEL:
 * - User input is accepted via settings UI
 * - Token is NEVER stored in mdl_config_plugins
 * - Token is ALWAYS stored encrypted in local_mzi_config via config_manager
 * - Reading shows masked value (******) for security
 */
class admin_setting_encrypted_token extends admin_setting {

    /**
     * Constructor
     *
     * @param string $name unique ascii name
     * @param string $visiblename localised name
     * @param string $description localised long description
     * @param string $defaultsetting default value
     */
    public function __construct($name, $visiblename, $description, $defaultsetting = '') {
        $this->nosave = false;
        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * Get the current setting value - returns masked value for security
     *
     * @return mixed Current setting value (masked)
     */
    public function get_setting() {
        $token = config_manager::get_api_token();
        
        // Return masked value for security (show if token exists)
        if (!empty($token)) {
            return '********'; // Show masked value
        }
        
        return '';
    }

    /**
     * Store the setting value in encrypted storage
     *
     * @param string $data The setting value from form
     * @return string Empty string for success, or error message
     */
    public function write_setting($data) {
        // If user entered new token (not the masked value)
        if ($data !== '********' && !empty($data)) {
            if (config_manager::set_api_token($data)) {
                return ''; // Success
            } else {
                return get_string('errorsavingsetting', 'admin');
            }
        }
        
        // If empty, clear the token
        if (empty($data)) {
            config_manager::set_api_token('');
            return '';
        }
        
        // If unchanged (still showing ********), don't update
        return '';
    }

    /**
     * Return an XHTML string for the setting
     *
     * @param string $data Current setting value
     * @param string $query Search query
     * @return string XHTML for setting
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;
        
        $default = $this->get_defaultsetting();
        
        $context = (object) [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => $data,
            'forceltr' => $this->get_force_ltr(),
            'size' => 50,
        ];
        
        $element = '<input type="password" id="' . $context->id . '" name="' . $context->name . '" ' .
                   'value="' . s($context->value) . '" size="' . $context->size . '" />';
        
        $hint = '<div class="form-text text-muted"><strong>🔒 Security Note:</strong> ' .
                'Token is stored encrypted in database. Leave blank to use no token. ' .
                'Enter new value to update.</div>';
        
        return format_admin_setting($this, $this->visiblename, $element, $this->description, true, '', $default, $query) . $hint;
    }
}
