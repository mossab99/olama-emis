<?php
/** Pure validation cases run on CI without WordPress or a database. */
define('ABSPATH',__DIR__.'/');
class WP_Error { private $message; public function __construct($code,$message){$this->message=$message;} public function get_error_message(){return $this->message;} }
function is_wp_error($value){return $value instanceof WP_Error;}
function sanitize_text_field($value){return trim(strip_tags($value));}
require dirname(__DIR__).'/includes/class-olama-emis-staff.php';
function check_staff($ok,$message){if(!$ok)throw new RuntimeException($message);}
$service=new Olama_EMIS_Staff();
$input=array('identity_number'=>'٠٠١٢٣٤٥٦٧٨','identity_type'=>'personal','first_name'=>'ليلى','family_name'=>'الاختبار','birth_date'=>'2000-02-29','total_experience'=>'10','education_experience'=>'8');
$p=$service->prepare_person($input);check_staff(!is_wp_error($p)&&$p[0]['identity_number']==='0012345678','Text identity and Arabic digits');
foreach(array('birth_date'=>'2001-02-29','identity_number'=>'A123','identity_type'=>'passport','children_count'=>'-1','total_experience'=>'4','gender'=>'other')as$key=>$value)check_staff(is_wp_error($service->prepare_person(array_merge($input,array($key=>$value)))),'Invalid '.$key.' rejected');
$old=array('profile'=>$p[0]);$new=$service->prepare_person(array('phone'=>'0790000000'),$old,array('profile'=>$p[1]));check_staff(!is_wp_error($new)&&$new[0]['first_name']==='ليلى','Absent columns preserved');
$work=array(array('subject'=>'رياضيات','grade'=>'العاشر','section'=>'أ','weekly_periods'=>'30'));
check_staff(!is_wp_error(Olama_EMIS_Staff::validate_workloads($work)),'Loads above provisional 26 allowed');
check_staff(is_wp_error(Olama_EMIS_Staff::validate_workloads(array($work[0],$work[0]))),'Repeated teaching assignment rejected');
check_staff(is_wp_error(Olama_EMIS_Staff::validate_workloads(array(array('subject'=>'علوم','weekly_periods'=>'1.5')))),'Fractional period rejected');
check_staff(is_wp_error(Olama_EMIS_Staff::validate_workloads(array('bad'))),'Malformed rows rejected');
echo "Staff validation passed.\n";
