<?php
/**
 * Plugin Name: OLAMA EMIS
 * Description: Ministry school, campus, staff and student statistical records.
 * Version: 0.4.0
 * Author: OLAMA
 * Text Domain: olama-emis
 */

if (!defined('ABSPATH')) exit;

define('OLAMA_EMIS_VERSION', '0.4.0');
define('OLAMA_EMIS_PATH', plugin_dir_path(__FILE__));
define('OLAMA_EMIS_URL', plugin_dir_url(__FILE__));

require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-schema.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-students.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-schools.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-import.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-admin.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-school-admin.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-campus.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-campus-admin.php';

require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-staff.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-staff-export.php';
require_once OLAMA_EMIS_PATH . 'includes/class-olama-emis-staff-admin.php';

function olama_emis_install() {
    if (!Olama_EMIS_Schema::migrate_table_names()) return false;
    Olama_EMIS_Students::install();
    Olama_EMIS_Schools::install();
    if (!Olama_EMIS_Campus::install()) return false;
    if (!Olama_EMIS_Staff::install()) return false;
    update_option('olama_emis_schema_version', OLAMA_EMIS_VERSION, false);
    return true;
}
register_activation_hook(__FILE__, 'olama_emis_install');
add_action('plugins_loaded', static function () {
    if (get_option('olama_emis_schema_version') !== OLAMA_EMIS_VERSION && !olama_emis_install()) {
        add_action('admin_notices', static function () {
            if (current_user_can('olama_users_ministry_view')) echo '<div class="notice notice-error"><p>OLAMA EMIS: تعذر إعداد جداول EMIS. راجع تعارض أسماء الجداول وصلاحية إعادة التسمية ودعم InnoDB.</p></div>';
        });
        return;
    }
    if (is_admin()) {
        new Olama_EMIS_Admin();
        new Olama_EMIS_School_Admin();
        new Olama_EMIS_Campus_Admin();
        new Olama_EMIS_Staff_Admin();
    }
}, 45);

function olama_emis_students() {
    static $service;
    if (!$service) $service = new Olama_EMIS_Students();
    return $service;
}
