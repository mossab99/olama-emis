<?php
/**
 * Plugin Name: OLAMA EMIS
 * Description: Ministry statistical data modules, beginning with student enrollment and demographics.
 * Version: 0.1.0
 * Author: OLAMA
 * Text Domain: olama-emis
 */

if (!defined('ABSPATH')) exit;

define('OLAMA_EMIS_VERSION', '0.1.0');
define('OLAMA_EMIS_PATH', plugin_dir_path(__FILE__));
define('OLAMA_EMIS_URL', plugin_dir_url(__FILE__));

require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-students.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-import.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-admin.php';

register_activation_hook(__FILE__, array('Olama_EMIS_Students', 'install'));
add_action('plugins_loaded', static function () {
    if (get_option('olama_emis_schema_version') !== OLAMA_EMIS_VERSION) Olama_EMIS_Students::install();
    if (is_admin()) new Olama_EMIS_Admin();
}, 45);

function olama_emis_students() {
    static $service;
    if (!$service) $service = new Olama_EMIS_Students();
    return $service;
}
