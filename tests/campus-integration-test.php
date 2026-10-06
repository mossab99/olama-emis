<?php
/** Run only against an isolated, disposable MySQL database named emis_test_*. */
$root = getenv('EMIS_TEST_WP_ROOT');
$db_name = getenv('EMIS_TEST_DB_NAME');
if (!$root || !preg_match('/^emis_test_[a-z0-9]+$/', (string) $db_name)) {
    fwrite(STDERR,"Set EMIS_TEST_WP_ROOT and a disposable EMIS_TEST_DB_NAME (emis_test_*).\n");
    exit(2);
}
$host = getenv('EMIS_TEST_DB_HOST') ?: '127.0.0.1:13306';
$user = getenv('EMIS_TEST_DB_USER') ?: 'root';
$password = getenv('EMIS_TEST_DB_PASSWORD') ?: '';
$parts = explode(':',$host);
$connection = new mysqli($parts[0],$user,$password,'',isset($parts[1]) ? (int) $parts[1] : 3306);
$connection->query("CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
define('ABSPATH',rtrim(str_replace('\\','/',$root),'/') . '/');
define('DB_NAME',$db_name); define('DB_USER',$user); define('DB_PASSWORD',$password); define('DB_HOST',$host);
define('DB_CHARSET','utf8mb4'); define('DB_COLLATE','');
define('WP_INSTALLING',true); define('WP_DEBUG',false); define('WP_USE_THEMES',false);
define('WP_SITEURL','http://emis-test.invalid'); define('WP_HOME','http://emis-test.invalid');
$table_prefix = 'test_';
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta(wp_get_db_schema());
require dirname(__DIR__) . '/includes/class-olama-emis-schema.php';
require dirname(__DIR__) . '/includes/class-olama-emis-students.php';
require dirname(__DIR__) . '/includes/class-olama-emis-schools.php';
require dirname(__DIR__) . '/includes/class-olama-emis-campus.php';

function expect($condition,$message) { if (!$condition) throw new RuntimeException($message); }
function success($value,$message) { expect(!is_wp_error($value),$message . (is_wp_error($value) ? ': ' . $value->get_error_message() : '')); return $value; }

try {
    Olama_EMIS_Students::install();
    Olama_EMIS_Schools::install();
    expect(Olama_EMIS_Campus::install(),'Campus tables use InnoDB');
    expect(Olama_EMIS_Campus::install(),'Repeatable schema upgrade');
    $wpdb->query("CREATE TABLE test_olama_core_student_years (school_id varchar(50), school_name varchar(100), study_year varchar(20)) ENGINE=InnoDB");
    $wpdb->insert('test_olama_core_student_years',array('school_id'=>'1','school_name'=>'مدرسة الاختبار','study_year'=>'2025-2026'));
    $service = new Olama_EMIS_Campus();
    $base = array('school_id'=>'1','study_year'=>'2025-2026','revision'=>0,'building_number'=>'1005','national_id'=>'00999');
    $building_id = success($service->save_building($base),'Create building');
    $context = array('school_id'=>'1','study_year'=>'2025-2026','building_id'=>$building_id,'revision'=>1);
    success($service->save_floor(array_merge($context,array('floor_number'=>'-1','floor_name'=>'التسوية','area'=>'120.25'))),'Save basement');
    $floor = $service->floors($building_id,'2025-2026')[0];
    expect((int) $floor['floor_number'] === -1 && (float) $floor['area'] === 120.25,'Signed floors and decimal area survive');
    expect(is_wp_error($service->save_floor(array_merge($context,array('floor_number'=>'1','floor_name'=>'الأول')))),'Stale edit rejected');
    $context['revision'] = 2;
    $room = array('room_number'=>'B01','floor_id'=>$floor['id'],'use_kind'=>'classroom','area'=>'20.5','class_type'=>'واحدة','grade'=>'الأول','section'=>'أ');
    success($service->save_rooms($context,array($room),'classroom'),'Create classroom');
    $saved = $service->rooms($building_id,'2025-2026','classroom')[0];
    $context['revision'] = 3;
    $new = $room; $new['room_number'] = 'B02';
    $duplicate = $room; $duplicate['use_kind'] = 'facility';
    expect(is_wp_error($service->save_rooms($context,array($new,$duplicate),'facility')),'Duplicate across room modules rejected');
    expect(count($service->rooms($building_id,'2025-2026')) === 1,'Failed batch rolled back all rows');
    expect((int) $service->building($building_id,'2025-2026')['revision'] === 3,'Failed batch did not consume revision');
    $row = array_merge($room,array('room_id'=>$saved['room_id'],'use_kind'=>'facility'));
    success($service->save_rooms($context,array($row),'classroom'),'Switch use');
    expect(count($service->rooms($building_id,'2025-2026','classroom')) === 0,'Moved out of classroom list');
    expect(count($service->rooms($building_id,'2025-2026','facility')) === 1,'Moved into facility list');
    $context['revision'] = 4;
    $row = array_merge($row,array('facility_type'=>'دورات مياه','summary_category'=>'دورات مياه','sanitary_units'=>'3'));
    success($service->save_rooms($context,array($row),'facility'),'Save facilities');
    expect($service->school_summary('1','2025-2026')['totals']['sanitary_units'] === 3,'Count toilet units, not rooms');
    $next = array_merge($base,array('building_id'=>$building_id,'study_year'=>'2026-2027','revision'=>0));
    success($service->save_building($next),'Create next annual snapshot');
    $next_context = array_merge($context,array('study_year'=>'2026-2027','revision'=>1));
    success($service->save_floor(array_merge($next_context,array('floor_number'=>'-1','floor_name'=>'التسوية','area'=>'125'))),'New floor snapshot');
    $next_context['revision'] = 2;
    success($service->save_rooms($next_context,array($room),'classroom'),'Reuse physical room in another year');
    expect($service->rooms($building_id,'2026-2027')[0]['room_id'] === $saved['room_id'],'Room identity stays stable across years');
    expect($service->rooms($building_id,'2025-2026')[0]['use_kind'] === 'facility','Previous annual use remains intact');
    $large_batch = array();
    for ($i=1;$i<=100;$i++) $large_batch[] = array_merge($room,array('room_number'=>sprintf('C%04d',$i)));
    $next_context['revision'] = 3;
    success($service->save_rooms($next_context,$large_batch,'classroom'),'Save 100-room batch');
    expect(count($service->rooms($building_id,'2026-2027')) === 101,'Rows omitted from a batch remain intact');
    $context['revision'] = 5;
    $row['archived'] = 1;
    success($service->save_rooms($context,array($row),'facility'),'Archive annual room');
    expect($service->school_summary('1','2025-2026')['totals']['facilities'] === 0,'Archived annual room excluded');
    expect(count($service->rooms($building_id,'2026-2027','classroom')) === 101,'Other year stays active');
    $context['revision'] = 6;
    $wrong_floor = $room; $wrong_floor['room_number'] = 'X'; $wrong_floor['floor_id'] = 99999;
    expect(is_wp_error($service->save_rooms($context,array($wrong_floor),'classroom')),'Unrelated floor rejected');
    $bad = $room; $bad['area'] = '-1';
    expect(is_wp_error($service->save_rooms($context,array($bad),'classroom')),'Negative area rejected');
    $bad = $room; $bad['room_number'] = 'b01';
    expect(is_wp_error($service->save_rooms($context,array($bad),'classroom')),'Case variants cannot bypass existing room uniqueness');
    expect(is_wp_error($service->save_building(array_merge($base,array('school_id'=>'999')))),'Unknown school rejected');
    require dirname(__DIR__) . '/includes/class-olama-emis-campus-admin.php';
    define('OLAMA_EMIS_VERSION','test');
    update_option('olama_emis_schema_version',OLAMA_EMIS_VERSION);
    $cap_filter = static function ($caps) { $caps['olama_users_ministry_view'] = true; $caps['olama_users_ministry_configure'] = true; return $caps; };
    add_filter('user_has_cap',$cap_filter);
    $admin = new Olama_EMIS_Campus_Admin();
    foreach (array('buildings','classrooms','facilities') as $module) {
        $_GET = array('page'=>'olama-emis-' . $module,'school_id'=>'1','study_year'=>'2026-2027','building_id'=>$building_id,'room_page'=>$module === 'classrooms' ? 2 : 1);
        ob_start(); $admin->page(); $html = ob_get_clean();
        expect(strpos($html,'olama_emis_save_campus') !== false,'Rendered save action: ' . $module);
        if ($module !== 'buildings') expect(strpos($html,'olama-emis__row-template') !== false,'Rendered grid: ' . $module);
        if ($module === 'classrooms') expect(substr_count($html,'data-room-id=') === 2,'Second page renders one saved room and a new-row template');
        $preview_dir = getenv('EMIS_TEST_PREVIEW_DIR');
        if ($preview_dir && is_dir($preview_dir)) {
            $css = file_get_contents(ABSPATH . 'wp-includes/css/buttons.min.css') . file_get_contents(dirname(__DIR__) . '/assets/admin.css');
            $js = file_get_contents(dirname(__DIR__) . '/assets/campus.js');
            file_put_contents($preview_dir . '/' . $module . '.html','<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>EMIS ' . $module . '</title><style>body{background:#f0f0f1;font-family:Arial,sans-serif;margin:20px}input,select{padding:8px;border:1px solid #aebdc7}button{cursor:pointer}</style><style>' . $css . '</style><body class="wp-core-ui">' . $html . '<script>' . $js . '</script></body></html>');
        }
    }
    remove_filter('user_has_cap',$cap_filter);
    add_filter('user_has_cap',static function ($caps) { $caps['olama_users_ministry_view'] = true; $caps['olama_users_ministry_configure'] = false; return $caps; });
    ob_start(); $admin->page(); $readonly_html = ob_get_clean();
    expect(strpos($readonly_html,'fieldset disabled') !== false,'Viewer fields disabled');
    expect(strpos($readonly_html,'olama-emis__add-room') === false,'Viewer cannot add rooms');
    success($service->save_building(array_merge($base,array('building_id'=>$building_id,'revision'=>6,'archived'=>1))),'Archive annual building');
    expect(is_wp_error($service->save_rooms(array_merge($context,array('revision'=>7)),array($new),'classroom')),'Archived building rejects room writes');
    require __DIR__ . '/staff-integration-cases.php';
    $before = array();
    $renames = array();
    foreach (Olama_EMIS_Schema::table_suffixes() as $suffix) {
        $table = $wpdb->prefix . 'olama_emis_' . $suffix;
        $before[$suffix] = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id", ARRAY_A);
        $renames[] = "`{$table}` TO `{$wpdb->prefix}emis_{$suffix}`";
    }
    expect(false !== $wpdb->query('RENAME TABLE ' . implode(', ', $renames)), 'Simulate legacy table names');
    $wpdb->query("CREATE TABLE {$wpdb->prefix}olama_emis_schools (id int)");
    expect(!Olama_EMIS_Schema::migrate_table_names(), 'Conflicting tables stop migration');
    expect($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}emis_buildings'") !== null, 'Conflict leaves all legacy tables intact');
    $wpdb->query("DROP TABLE {$wpdb->prefix}olama_emis_schools");
    expect(Olama_EMIS_Schema::migrate_table_names(), 'Migrate legacy names');
    expect(Olama_EMIS_Schema::migrate_table_names(), 'Repeat migration safely');
    foreach ($before as $suffix => $rows) {
        $table = $wpdb->prefix . 'olama_emis_' . $suffix;
        expect($rows === $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id", ARRAY_A), 'Migration preserves records: ' . $suffix);
    }
    echo "Campus MySQL integration passed: schema upgrades, identity, years, rollback, duplicate rooms, stale edits, floor scope, archival and unit summaries.\n";
} finally {
    $connection->query("DROP DATABASE `{$db_name}`");
}
