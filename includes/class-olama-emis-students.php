<?php
if (!defined('ABSPATH')) exit;

/** A year-specific Ministry dossier. OLAMA Core remains the Oracle student master. */
final class Olama_EMIS_Students {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'olama_emis_students';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            student_uid varchar(100) NOT NULL,
            study_year varchar(20) NOT NULL,
            school_id varchar(50) NOT NULL,
            building_number varchar(50) NOT NULL DEFAULT '',
            classroom_number varchar(50) NOT NULL DEFAULT '',
            education_stream varchar(100) NOT NULL DEFAULT '',
            grade varchar(100) NOT NULL DEFAULT '',
            section varchar(100) NOT NULL DEFAULT '',
            national_id varchar(60) NOT NULL DEFAULT '',
            identity_document_type varchar(30) NOT NULL DEFAULT '',
            identity_document_number varchar(80) NOT NULL DEFAULT '',
            first_name varchar(100) NOT NULL DEFAULT '',
            father_name varchar(100) NOT NULL DEFAULT '',
            grandfather_name varchar(100) NOT NULL DEFAULT '',
            family_name varchar(100) NOT NULL DEFAULT '',
            gender varchar(30) NOT NULL DEFAULT '',
            birth_date date DEFAULT NULL,
            nationality varchar(100) NOT NULL DEFAULT '',
            civil_record_number varchar(100) NOT NULL DEFAULT '',
            civil_record_place varchar(100) NOT NULL DEFAULT '',
            disability_type varchar(150) NOT NULL DEFAULT '',
            guardian_name varchar(200) NOT NULL DEFAULT '',
            guardian_relation varchar(100) NOT NULL DEFAULT '',
            guardian_phone varchar(40) NOT NULL DEFAULT '',
            enrollment_status varchar(30) NOT NULL DEFAULT '',
            extra_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_year (student_uid,study_year),
            KEY cohort (school_id,study_year,building_number,classroom_number),
            KEY national_lookup (national_id)
        ) {$charset};");
    }

    public static function fields() {
        return array(
            'student_uid' => 'معرف الطالب في OLAMA', 'study_year' => 'العام الدراسي', 'school_id' => 'المدرسة',
            'building_number' => 'رقم البناء', 'classroom_number' => 'رقم الغرفة الصفية',
            'education_stream' => 'الفرع التعليمي', 'grade' => 'الصف', 'section' => 'الشعبة',
            'national_id' => 'الرقم الوطني للطالب', 'identity_document_type' => 'نوع وثيقة غير الأردني',
            'identity_document_number' => 'رقم وثيقة غير الأردني', 'first_name' => 'الاسم الأول',
            'father_name' => 'اسم الأب', 'grandfather_name' => 'اسم الجد', 'family_name' => 'اسم العائلة',
            'gender' => 'الجنس', 'birth_date' => 'تاريخ الميلاد', 'nationality' => 'الجنسية',
            'civil_record_number' => 'رقم القيد', 'civil_record_place' => 'مكان صدور القيد',
            'disability_type' => 'نوع الإعاقة إن وجدت', 'guardian_name' => 'اسم ولي الأمر',
            'guardian_relation' => 'صلة القرابة', 'guardian_phone' => 'هاتف ولي الأمر',
            'enrollment_status' => 'حالة القيد',
        );
    }

    public function find($student_uid, $study_year) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE student_uid = %s AND study_year = %s', $student_uid, $study_year), ARRAY_A);
    }

    public function save(array $input) {
        global $wpdb;
        $lookup_uid = isset($input['student_uid']) ? sanitize_text_field((string) $input['student_uid']) : '';
        $lookup_year = isset($input['study_year']) ? str_replace('/', '-', sanitize_text_field((string) $input['study_year'])) : '';
        $existing = $lookup_uid && $lookup_year ? $this->find($lookup_uid, $lookup_year) : null;
        $data = array();
        foreach (self::fields() as $key => $label) {
            $data[$key] = isset($input[$key]) ? sanitize_text_field((string) $input[$key]) : ($existing && isset($existing[$key]) ? $existing[$key] : '');
        }
        foreach (array('student_uid', 'study_year', 'school_id') as $key) {
            if ($data[$key] === '') return new WP_Error('emis_required', sprintf('Missing %s.', $key));
        }
        if (!preg_match('~^\d{4}[/-]\d{4}$~', $data['study_year'])) return new WP_Error('emis_year', 'Invalid academic year.');
        $data['study_year'] = str_replace('/', '-', $data['study_year']);
        if ($data['birth_date'] !== '' && !wp_checkdate((int) substr($data['birth_date'], 5, 2), (int) substr($data['birth_date'], 8, 2), (int) substr($data['birth_date'], 0, 4), $data['birth_date'])) {
            return new WP_Error('emis_birth_date', 'Invalid birth date.');
        }
        if ($data['birth_date'] === '') $data['birth_date'] = null;
        if ($data['identity_document_type'] !== '' && !in_array($data['identity_document_type'], array('جواز سفر', 'هوية'), true)) return new WP_Error('emis_document', 'Only passport or ID card is accepted.');
        if ($data['enrollment_status'] !== '' && !in_array($data['enrollment_status'], array('مستجد', 'معيد', 'منقول'), true)) return new WP_Error('emis_status', 'Invalid enrollment status.');

        if (!function_exists('olama_core')) return new WP_Error('emis_core', 'OLAMA Core is required.');
        $student = olama_core()->students()->get_by_uid($data['student_uid']);
        $year = olama_core()->student_years()->get_current_year($data['student_uid'], $data['study_year']);
        if (!$student || !$year || (string) $year['school_id'] !== $data['school_id']) return new WP_Error('emis_enrollment', 'Student is not enrolled in this school and year.');

        if ($data['national_id'] !== '') {
            $duplicate = $wpdb->get_var($wpdb->prepare('SELECT student_uid FROM ' . self::table() . ' WHERE national_id = %s AND student_uid <> %s LIMIT 1', $data['national_id'], $data['student_uid']));
            if ($duplicate) return new WP_Error('emis_duplicate', 'National number is already assigned to another student.');
            $master = $wpdb->get_var($wpdb->prepare('SELECT student_uid FROM ' . $wpdb->prefix . 'olama_core_students WHERE student_national_no = %s AND student_uid <> %s LIMIT 1', $data['national_id'], $data['student_uid']));
            if ($master) return new WP_Error('emis_duplicate_core', 'National number belongs to another OLAMA Core student.');
        }
        $data['updated_at'] = current_time('mysql');
        if ($existing) {
            $ok = $wpdb->update(self::table(), $data, array('id' => (int) $existing['id']));
        } else {
            $data['created_at'] = $data['updated_at'];
            $ok = $wpdb->insert(self::table(), $data);
        }
        return false === $ok ? new WP_Error('emis_database', $wpdb->last_error ?: 'Could not save student dossier.') : true;
    }

    public function roster(array $filter) {
        global $wpdb;
        foreach (array('school_id', 'study_year', 'building_number', 'classroom_number', 'education_stream', 'grade', 'section') as $key) $filter[$key] = isset($filter[$key]) ? sanitize_text_field($filter[$key]) : '';
        if (!$filter['school_id'] || !$filter['study_year']) return array();
        $where = array('y.school_id = %s', 'y.study_year = %s');
        $args = array($filter['school_id'], str_replace('/', '-', $filter['study_year']));
        foreach (array('building_number', 'classroom_number') as $key) if ($filter[$key] !== '') { $where[] = 'e.' . $key . ' = %s'; $args[] = $filter[$key]; }
        if ($filter['education_stream'] !== '') { $where[] = 'COALESCE(NULLIF(e.education_stream, \'\'), y.branch_name) = %s'; $args[] = $filter['education_stream']; }
        if ($filter['grade'] !== '') { $where[] = 'COALESCE(NULLIF(e.grade, \'\'), y.class_name) = %s'; $args[] = $filter['grade']; }
        if ($filter['section'] !== '') { $where[] = 'COALESCE(NULLIF(e.section, \'\'), y.section_name) = %s'; $args[] = $filter['section']; }
        $sql = 'SELECT y.student_uid, y.study_year, y.school_id, '
            . 'COALESCE(e.building_number, \'\') AS building_number, COALESCE(e.classroom_number, \'\') AS classroom_number, '
            . 'COALESCE(NULLIF(e.education_stream, \'\'), y.branch_name) AS education_stream, '
            . 'COALESCE(NULLIF(e.grade, \'\'), y.class_name) AS grade, COALESCE(NULLIF(e.section, \'\'), y.section_name) AS section, '
            . 'COALESCE(NULLIF(e.national_id, \'\'), s.student_national_no) AS national_id, COALESCE(NULLIF(e.nationality, \'\'), s.nationality) AS nationality, '
            . 'COALESCE(e.first_name, \'\') AS first_name, COALESCE(e.father_name, \'\') AS father_name, '
            . 'COALESCE(e.grandfather_name, \'\') AS grandfather_name, COALESCE(e.family_name, \'\') AS family_name, '
            . 's.student_name, COALESCE(e.enrollment_status, \'\') AS enrollment_status, e.id AS dossier_id '
            . 'FROM ' . $wpdb->prefix . 'olama_core_student_years y '
            . 'JOIN ' . $wpdb->prefix . 'olama_core_students s ON s.student_uid = y.student_uid '
            . 'LEFT JOIN ' . self::table() . ' e ON e.student_uid = y.student_uid AND e.study_year = y.study_year '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY grade, section, s.student_name LIMIT 2000';
        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
    }
}
