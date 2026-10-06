<?php
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function is_email($value) { return (bool) filter_var($value, FILTER_VALIDATE_EMAIL); }
require dirname(__DIR__) . '/includes/class-olama-emis-schools.php';

$input = array('school_name' => 'مدرسة الاختبار', 'school_number' => '117571', 'school_national_id' => '711234', 'governorate' => 'عمّان (العاصمة)', 'latitude' => '31.95', 'longitude' => '35.93', 'land_area' => '1250.5');
$profile = Olama_EMIS_Schools::validate_profile($input);
if (is_wp_error($profile) || $profile['school_number'] !== '117571' || $profile['school_national_id'] !== '711234' || $profile['land_area'] !== '1250.5') throw new RuntimeException('Valid school profile rejected');
$bad = $input; $bad['latitude'] = '95';
if (!is_wp_error(Olama_EMIS_Schools::validate_profile($bad))) throw new RuntimeException('Invalid coordinate accepted');
$bad = $input; $bad['school_national_id'] = '117571-A';
if (!is_wp_error(Olama_EMIS_Schools::validate_profile($bad))) throw new RuntimeException('Invalid national ID accepted');
$bad = $input; $bad['governorate'] = 'غير معروفة';
if (!is_wp_error(Olama_EMIS_Schools::validate_profile($bad))) throw new RuntimeException('Invalid governorate accepted');
echo "School profile validation passed.\n";
