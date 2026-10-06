<?php
if (!defined('ABSPATH')) exit;

final class Olama_EMIS_Admin {
    private $slug = 'olama-emis-students';

    public function __construct() {
        add_action('admin_menu', array($this, 'menu'), 30);
        add_action('admin_post_olama_emis_save_student', array($this, 'save'));
        add_action('admin_post_olama_emis_import', array($this, 'import'));
        add_action('admin_post_olama_emis_template', array($this, 'template'));
        add_action('admin_post_olama_emis_roster_pdf', array($this, 'pdf'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
    }

    public function menu() {
        add_menu_page('OLAMA EMIS', 'OLAMA EMIS', 'olama_users_ministry_view', 'olama-emis', array($this, 'page'), 'dashicons-chart-area', 29);
        add_submenu_page('olama-emis', 'البيانات الأساسية للطالب', 'البيانات الأساسية للطالب', 'olama_users_ministry_view', $this->slug, array($this, 'page'));
    }

    public function assets($hook) {
        if (strpos($hook, 'olama-emis') !== false) wp_enqueue_style('olama-emis-admin', OLAMA_EMIS_URL . 'assets/admin.css', array(), OLAMA_EMIS_VERSION);
    }

    private function authorize($cap = 'olama_users_ministry_view') {
        if (!current_user_can($cap)) wp_die('Access denied.', '', array('response' => 403));
    }

    private function redirect($message) {
        wp_safe_redirect(add_query_arg(array('page' => $this->slug, 'emis_notice' => rawurlencode($message)), admin_url('admin.php')));
        exit;
    }

    public function page() {
        $this->authorize();
        $year = isset($_GET['study_year']) ? sanitize_text_field(wp_unslash($_GET['study_year'])) : '2025-2026';
        $school = isset($_GET['school_id']) ? sanitize_text_field(wp_unslash($_GET['school_id'])) : '';
        $grade = isset($_GET['grade']) ? sanitize_text_field(wp_unslash($_GET['grade'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
        $building_number = isset($_GET['building_number']) ? sanitize_text_field(wp_unslash($_GET['building_number'])) : '';
        $classroom_number = isset($_GET['classroom_number']) ? sanitize_text_field(wp_unslash($_GET['classroom_number'])) : '';
        $education_stream = isset($_GET['education_stream']) ? sanitize_text_field(wp_unslash($_GET['education_stream'])) : '';
        $uid = isset($_GET['student_uid']) ? sanitize_text_field(wp_unslash($_GET['student_uid'])) : '';
        $schools = function_exists('olama_core') ? olama_core()->student_statistics()->schools($year) : array();
        $roster = $school ? olama_emis_students()->roster(array('school_id' => $school, 'study_year' => $year, 'building_number' => $building_number, 'classroom_number' => $classroom_number, 'education_stream' => $education_stream, 'grade' => $grade, 'section' => $section)) : array();
        $saved_count = 0;
        foreach ($roster as $entry) if (!empty($entry['dossier_id'])) $saved_count++;
        $record = $uid ? olama_emis_students()->find($uid, $year) : null;
        $core_student = $uid && function_exists('olama_core') ? olama_core()->students()->get_by_uid($uid) : null;
        $enrollment = $uid && function_exists('olama_core') ? olama_core()->student_years()->get_current_year($uid, $year) : null;
        echo '<div class="wrap olama-emis" dir="rtl"><header class="olama-emis__hero"><span>OLAMA EMIS · المرحلة ٧</span><h1>البيانات الأساسية للطالب</h1><p>سجل الوزارة لكل طالب وسنة. تبقى هوية الطالب والقيد الأصلي في OLAMA Core.</p><p><a class="button" href="' . esc_url(admin_url('admin.php?page=olama-users-ministry')) . '">طلبات الأسر ومراجعتها</a></p></header>';
        if (isset($_GET['emis_notice'])) echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['emis_notice']))) . '</p></div>';
        $import_errors = get_transient('olama_emis_import_' . get_current_user_id());
        if (is_array($import_errors) && $import_errors) {
            echo '<details class="olama-emis__panel"><summary>تقرير أخطاء الاستيراد (' . esc_html(count($import_errors)) . ')</summary><ol>';
            foreach ($import_errors as $error) echo '<li>' . esc_html($error) . '</li>';
            echo '</ol></details>';
        }
        echo '<div class="olama-emis__cards"><div><strong>' . esc_html(count($roster)) . '</strong><span>طلاب القيد المطابقون</span></div><div><strong>' . esc_html($saved_count) . '</strong><span>ملفات محفوظة</span></div><div><strong>' . esc_html(count($roster) - $saved_count) . '</strong><span>بانتظار الاستكمال</span></div></div>';
        echo '<section class="olama-emis__panel"><h2>تحديد المدرسة والصف</h2><form method="get" class="olama-emis__filters"><input type="hidden" name="page" value="' . esc_attr($this->slug) . '"><label>العام الدراسي<input name="study_year" value="' . esc_attr($year) . '" required></label><label>المدرسة<select name="school_id"><option value="">اختر المدرسة</option>';
        foreach ($schools as $item) echo '<option value="' . esc_attr($item['school_id']) . '" ' . selected($school, $item['school_id'], false) . '>' . esc_html($item['school_name'] . ' (' . $item['school_id'] . ')') . '</option>';
        echo '</select></label><label>رقم البناء<input name="building_number" value="' . esc_attr($building_number) . '"></label><label>الغرفة الصفية<input name="classroom_number" value="' . esc_attr($classroom_number) . '"></label><label>الفرع التعليمي<input name="education_stream" value="' . esc_attr($education_stream) . '"></label><label>الصف<input name="grade" value="' . esc_attr($grade) . '"></label><label>الشعبة<input name="section" value="' . esc_attr($section) . '"></label><button class="button button-primary">عرض</button></form></section>';
        if ($school) {
            $base = admin_url('admin-post.php');
            echo '<section class="olama-emis__panel"><div class="olama-emis__panel-head"><h2>سجل الصف</h2><div><a class="button" href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'olama_emis_template'), $base), 'olama_emis_template')) . '">قالب CSV</a> ';
            echo '<a class="button" href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'olama_emis_roster_pdf', 'school_id' => $school, 'study_year' => $year, 'building_number' => $building_number, 'classroom_number' => $classroom_number, 'education_stream' => $education_stream, 'grade' => $grade, 'section' => $section), $base), 'olama_emis_roster_pdf')) . '">طباعة PDF</a></div></div>';
            echo '<div class="olama-emis__scroll"><table class="widefat striped"><thead><tr><th>معرف الطالب</th><th>الاسم</th><th>الرقم الوطني</th><th>الصف</th><th>الشعبة</th><th>الحالة</th><th></th></tr></thead><tbody>';
            foreach ($roster as $row) {
                $url = add_query_arg(array('page' => $this->slug, 'school_id' => $school, 'study_year' => $year, 'grade' => $grade, 'section' => $section, 'building_number' => $building_number, 'classroom_number' => $classroom_number, 'education_stream' => $education_stream, 'student_uid' => $row['student_uid']), admin_url('admin.php'));
                $display_name = trim($row['first_name'] . ' ' . $row['father_name'] . ' ' . $row['grandfather_name'] . ' ' . $row['family_name']);
                echo '<tr><td>' . esc_html($row['student_uid']) . '</td><td>' . esc_html($display_name ?: $row['student_name']) . '</td><td>' . esc_html($row['national_id']) . '</td><td>' . esc_html($row['grade']) . '</td><td>' . esc_html($row['section']) . '</td><td>' . esc_html($row['dossier_id'] ? $row['enrollment_status'] : 'بانتظار الاستكمال') . '</td><td><a href="' . esc_url($url) . '">فتح</a></td></tr>';
            }
            if (!$roster) echo '<tr><td colspan="7">لا يوجد طلاب في القيد المطابق للتصفية.</td></tr>';
            echo '</tbody></table></div></section>';
            if (current_user_can('olama_users_ministry_configure')) echo '<section class="olama-emis__panel"><h2>استيراد دفعة CSV / XLSX</h2><p>يجب أن يحتوي الملف على student_uid. تُراجع كل صفوف الملف مقابل قيد OLAMA Core، وتظهر أخطاء الصفوف منفصلة.</p><form method="post" enctype="multipart/form-data" action="' . esc_url($base) . '">';
            if (current_user_can('olama_users_ministry_configure')) {
            wp_nonce_field('olama_emis_import');
            echo '<input type="hidden" name="action" value="olama_emis_import"><input type="hidden" name="school_id" value="' . esc_attr($school) . '"><input type="hidden" name="study_year" value="' . esc_attr($year) . '"><input type="hidden" name="building_number" value="' . esc_attr($building_number) . '"><input type="hidden" name="classroom_number" value="' . esc_attr($classroom_number) . '"><input type="hidden" name="education_stream" value="' . esc_attr($education_stream) . '"><input type="hidden" name="grade" value="' . esc_attr($grade) . '"><input type="hidden" name="section" value="' . esc_attr($section) . '"><input type="file" name="cohort_file" accept=".csv,.xlsx" required> <button class="button button-primary">استيراد</button></form></section>';
            }
        }
        echo '<section class="olama-emis__panel"><h2>ملف طالب واحد</h2><form method="get" class="olama-emis__filters"><input type="hidden" name="page" value="' . esc_attr($this->slug) . '"><input type="hidden" name="study_year" value="' . esc_attr($year) . '"><input type="hidden" name="school_id" value="' . esc_attr($school) . '"><label>معرف OLAMA Core<input name="student_uid" value="' . esc_attr($uid) . '" required></label><button class="button">تحميل</button></form>';
        if ($uid && (!$core_student || !$enrollment)) echo '<p class="notice notice-error">لا يوجد قيد لهذا الطالب في العام المحدد.</p>';
        if ($core_student && $enrollment) {
            echo '<p>الطالب في OLAMA Core: <strong>' . esc_html($core_student['student_name']) . '</strong> · ' . esc_html($enrollment['school_name']) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('olama_emis_save_student');
            echo '<input type="hidden" name="action" value="olama_emis_save_student"><div class="olama-emis__fields">';
            $defaults = array('student_uid' => $uid, 'study_year' => $year, 'school_id' => $enrollment['school_id'], 'building_number' => $building_number, 'classroom_number' => $classroom_number, 'grade' => $enrollment['class_name'], 'section' => $enrollment['section_name'], 'education_stream' => $enrollment['branch_name'], 'national_id' => $core_student['student_national_no'], 'nationality' => $core_student['nationality'], 'gender' => $core_student['student_gender_name'], 'birth_date' => $core_student['birth_date']);
            $legacy = olama_core()->student_statistics()->evaluate($uid, $year);
            if (!is_wp_error($legacy) && !empty($legacy['fields'])) {
                $mapping = array('national_id' => 'national_id', 'document_type' => 'identity_document_type', 'civil_register' => 'civil_record_number', 'first_name' => 'first_name', 'father_name' => 'father_name', 'grandfather_name' => 'grandfather_name', 'family_name' => 'family_name', 'birth_date' => 'birth_date', 'nationality' => 'nationality', 'gender' => 'gender', 'guardian_name' => 'guardian_name', 'guardian_relation' => 'guardian_relation', 'guardian_phone' => 'guardian_phone');
                foreach ($mapping as $from => $to) if (isset($legacy['fields'][$from]['value']) && $legacy['fields'][$from]['value'] !== '') $defaults[$to] = $legacy['fields'][$from]['value'];
            }
            foreach (Olama_EMIS_Students::fields() as $key => $label) {
                $value = $record && isset($record[$key]) ? $record[$key] : (isset($defaults[$key]) ? $defaults[$key] : '');
                $readonly = in_array($key, array('student_uid', 'study_year', 'school_id'), true);
                echo '<label><span>' . esc_html($label) . '</span>';
                if ($readonly) echo '<input value="' . esc_attr($value) . '" readonly><input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                elseif ($key === 'identity_document_type' || $key === 'enrollment_status') {
                    $choices = $key === 'identity_document_type' ? array('جواز سفر', 'هوية') : array('مستجد', 'معيد', 'منقول');
                    echo '<select name="' . esc_attr($key) . '"><option value="">اختر</option>';
                    foreach ($choices as $choice) echo '<option value="' . esc_attr($choice) . '" ' . selected($value, $choice, false) . '>' . esc_html($choice) . '</option>';
                    echo '</select>';
                } else echo '<input name="' . esc_attr($key) . '" type="' . ($key === 'birth_date' ? 'date' : 'text') . '" value="' . esc_attr($value) . '">';
                echo '</label>';
            }
            echo '</div>';
            if (current_user_can('olama_users_ministry_configure')) echo '<p><button class="button button-primary">حفظ ملف الطالب</button></p>';
            echo '</form>';
        }
        echo '</section></div>';
    }

    public function save() {
        $this->authorize('olama_users_ministry_configure');
        check_admin_referer('olama_emis_save_student');
        $result = olama_emis_students()->save(wp_unslash($_POST));
        $this->redirect(is_wp_error($result) ? $result->get_error_message() : 'تم حفظ ملف الطالب.');
    }

    public function import() {
        $this->authorize('olama_users_ministry_configure');
        check_admin_referer('olama_emis_import');
        $file = isset($_FILES['cohort_file']) ? $_FILES['cohort_file'] : array();
        if (empty($file['tmp_name']) || !empty($file['error']) || !is_uploaded_file($file['tmp_name'])) $this->redirect('تعذر قراءة الملف المرفوع.');
        $rows = Olama_EMIS_Import::rows($file['tmp_name'], $file['name']);
        if (is_wp_error($rows)) $this->redirect($rows->get_error_message());
        $school = isset($_POST['school_id']) ? sanitize_text_field(wp_unslash($_POST['school_id'])) : '';
        $year = isset($_POST['study_year']) ? sanitize_text_field(wp_unslash($_POST['study_year'])) : '';
        $saved = 0; $errors = array();
        foreach ($rows as $index => $row) {
            $row['school_id'] = $school; $row['study_year'] = $year;
            foreach (array('building_number', 'classroom_number', 'education_stream', 'grade', 'section') as $key) {
                $header = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
                if ($header !== '' && (!isset($row[$key]) || $row[$key] === '')) $row[$key] = $header;
            }
            $result = olama_emis_students()->save($row);
            if (is_wp_error($result)) $errors[] = ($index + 2) . ': ' . $result->get_error_message(); else $saved++;
        }
        set_transient('olama_emis_import_' . get_current_user_id(), $errors, 10 * MINUTE_IN_SECONDS);
        $this->redirect(sprintf('تم حفظ %d طالب. أخطاء: %d. افتح تقرير الاستيراد أدناه.', $saved, count($errors)));
    }

    public function template() {
        $this->authorize(); check_admin_referer('olama_emis_template');
        nocache_headers(); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="olama-emis-students.csv"');
        $stream = fopen('php://output', 'wb'); fwrite($stream, "\xEF\xBB\xBF"); fputcsv($stream, array_keys(Olama_EMIS_Students::fields())); fclose($stream); exit;
    }

    public function pdf() {
        $this->authorize(); check_admin_referer('olama_emis_roster_pdf');
        $filter = array(); foreach (array('school_id', 'study_year', 'building_number', 'classroom_number', 'education_stream', 'grade', 'section') as $key) $filter[$key] = isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        $rows = olama_emis_students()->roster($filter);
        $autoload = WP_PLUGIN_DIR . '/olama-pdf-tools/vendor/autoload.php';
        if (!class_exists('TCPDF') && is_readable($autoload)) require_once $autoload;
        if (!class_exists('TCPDF')) wp_die('OLAMA PDF Tools / TCPDF is required for PDF export.');
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OLAMA EMIS'); $pdf->SetTitle('سجل الصف'); $pdf->SetMargins(12, 14, 12); $pdf->AddPage();
        $pdf->setRTL(true); $pdf->SetFont('dejavusans', '', 10);
        $pdf->writeHTML('<h2>سجل الصف · ' . esc_html($filter['study_year']) . '</h2><p>المدرسة: ' . esc_html($filter['school_id']) . ' · الصف: ' . esc_html($filter['grade']) . ' · الشعبة: ' . esc_html($filter['section']) . '</p>');
        $html = '<table border="1" cellpadding="5"><tr><th>م</th><th>معرف الطالب</th><th>الاسم</th><th>الرقم الوطني</th><th>الجنسية</th><th>الحالة</th></tr>';
        foreach ($rows as $i => $row) {
            $display_name = trim($row['first_name'] . ' ' . $row['father_name'] . ' ' . $row['grandfather_name'] . ' ' . $row['family_name']);
            $html .= '<tr><td>' . ($i + 1) . '</td><td>' . esc_html($row['student_uid']) . '</td><td>' . esc_html($display_name ?: $row['student_name']) . '</td><td>' . esc_html($row['national_id']) . '</td><td>' . esc_html($row['nationality']) . '</td><td>' . esc_html($row['enrollment_status']) . '</td></tr>';
        }
        $pdf->writeHTML($html . '</table>'); $pdf->Output('olama-class-roster.pdf', 'D'); exit;
    }
}
