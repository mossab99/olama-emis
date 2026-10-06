<?php
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
require dirname(__DIR__) . '/includes/class-olama-emis-students.php';
require dirname(__DIR__) . '/includes/class-olama-emis-import.php';

$csv = tempnam(sys_get_temp_dir(), 'emis');
file_put_contents($csv, "\xEF\xBB\xBFstudent_uid,national_id,birth_date\nORA-STU-1-2,123456,2020-02-03\n");
$rows = Olama_EMIS_Import::rows($csv, 'cohort.csv');
unlink($csv);
if (is_wp_error($rows) || count($rows) !== 1 || $rows[0]['national_id'] !== '123456') throw new RuntimeException('CSV import failed');

$xlsx = tempnam(sys_get_temp_dir(), 'emis');
$zip = new ZipArchive(); $zip->open($xlsx, ZipArchive::OVERWRITE);
$zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>student_uid</t></si><si><t>birth_date</t></si><si><t>ORA-STU-1-2</t></si></sst>');
$zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>43864</v></c></row></sheetData></worksheet>');
$zip->close();
$rows = Olama_EMIS_Import::rows($xlsx, 'cohort.xlsx');
unlink($xlsx);
if (is_wp_error($rows) || count($rows) !== 1 || $rows[0]['student_uid'] !== 'ORA-STU-1-2' || $rows[0]['birth_date'] !== '2020-02-03') throw new RuntimeException('XLSX import failed: ' . (is_wp_error($rows) ? $rows->get_error_message() : json_encode($rows)));
echo "Import parser passed CSV and XLSX fixtures.\n";
