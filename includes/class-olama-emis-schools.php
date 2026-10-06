<?php
if (!defined('ABSPATH')) exit;

/** Annual Ministry school profile. Core enrollment remains the school identity source. */
final class Olama_EMIS_Schools {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'olama_emis_schools';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            school_id varchar(50) NOT NULL,
            study_year varchar(20) NOT NULL,
            school_name varchar(255) NOT NULL DEFAULT '',
            school_number varchar(50) NOT NULL DEFAULT '',
            school_national_id varchar(50) NOT NULL DEFAULT '',
            profile_json longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            revision bigint(20) unsigned NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY school_year (school_id,study_year),
            KEY national_lookup (school_national_id)
        ) {$charset};");
    }

    /** Registry is provisional until the latest Ministry workbook is supplied. */
    public static function fields() {
        return array(
            'identity' => array(
                'school_name' => array('اسم المدرسة', 'A2', 'text'),
                'school_number' => array('رقم المدرسة الوزاري', '', 'text'),
                'school_national_id' => array('الرقم الوطني للمدرسة', 'A3', 'text'),
            ),
            'location' => array(
                'authority' => array('السلطة المشرفة', 'A5', 'text'),
                'directorate' => array('مديرية التربية والتعليم', 'C5', 'text'),
                'governorate' => array('المحافظة', 'E5', 'governorate'),
                'district' => array('اللواء', 'G5', 'text'),
                'subdistrict' => array('القضاء', 'I5', 'text'),
                'locality' => array('المدينة / القرية', 'K5', 'text'),
                'neighborhood' => array('الحي', 'A8', 'text'),
                'street' => array('الشارع', 'C8', 'text'),
                'postal_code' => array('الرمز البريدي', '', 'text'),
                'longitude' => array('خط الطول', 'G11', 'coordinate'),
                'latitude' => array('خط العرض', 'H11', 'coordinate'),
            ),
            'operations' => array(
                'founded_year' => array('سنة تأسيس المدرسة', 'E8', 'year'),
                'education_system' => array('نظام التعليم', 'E11', 'text'),
                'school_gender' => array('جنس المدرسة', 'F11', 'school_gender'),
                'period' => array('فترة المدرسة', 'C24', 'text'),
                'stages' => array('المراحل التعليمية', 'I11', 'text'),
                'lowest_grade' => array('أدنى صف', 'L11', 'text'),
                'highest_grade' => array('أعلى صف', 'K11', 'text'),
                'property_type' => array('ملكية المدرسة', 'A24', 'text'),
            ),
            'contact' => array(
                'phone' => array('هاتف المدرسة', 'G8', 'tel'),
                'website' => array('الموقع الإلكتروني', 'I8', 'url'),
                'email' => array('البريد الإلكتروني', '', 'email'),
                'founder_name' => array('اسم المؤسس حسب السجل التجاري', 'K8', 'text'),
                'general_manager' => array('اسم المدير العام إن وجد', 'A11', 'text'),
                'principal_name' => array('اسم مدير المدرسة', 'C11', 'text'),
            ),
            'campus' => array(
                'infrastructure_condition' => array('حالة البنية التحتية', 'D24', 'text'),
                'land_area' => array('مساحة أرض المدرسة م²', 'E24', 'number'),
                'basin_number' => array('رقم الحوض', 'G24', 'text'),
                'plot_number' => array('رقم القطعة', 'H24', 'text'),
                'yard_count' => array('عدد الساحات', 'I24', 'integer'),
                'yard_area' => array('مساحة الساحات م²', 'J24', 'number'),
                'playground_count' => array('عدد الملاعب', 'K24', 'integer'),
                'playground_area' => array('مساحة الملاعب م²', 'L24', 'number'),
                'parking_count' => array('عدد المواقف', 'A27', 'integer'),
                'parking_area' => array('مساحة المواقف م²', 'B27', 'number'),
                'future_expansion' => array('التوسع المستقبلي', 'C27', 'text'),
                'garden_area' => array('مساحة حديقة المدرسة م²', 'E27', 'number'),
                'accessible_passages' => array('ممرات ذوي الاحتياجات الخاصة', 'F27', 'text'),
                'construction_additions' => array('الإضافات الإنشائية', 'G27', 'text'),
                'combined_class_count' => array('عدد الصفوف المجمعة', 'I27', 'integer'),
                'mobile_class_count' => array('عدد الصفوف المتنقلة', 'K27', 'integer'),
                'wall_condition' => array('حالة سور المدرسة', 'A30', 'text'),
                'wall_maintenance' => array('صيانة السور', 'B30', 'text'),
                'water_available' => array('توفر المياه', 'C30', 'text'),
                'electricity_available' => array('توفر الكهرباء', 'D30', 'text'),
                'library_count' => array('عدد المكتبات', 'E30', 'integer'),
                'science_labs' => array('مختبرات العلوم', 'G30', 'integer'),
                'computer_labs' => array('مختبرات الحاسوب', 'H30', 'integer'),
                'language_labs' => array('مختبرات اللغات', 'I30', 'integer'),
                'service_staff_male' => array('العاملون بالخدمات / ذكور', 'J30', 'integer'),
                'service_staff_female' => array('العاملون بالخدمات / إناث', 'K30', 'integer'),
            ),
        );
    }

    public static function governorates() {
        return array('عمّان (العاصمة)', 'إربد', 'الزرقاء', 'المفرق', 'عجلون', 'جرش', 'مادبا', 'البلقاء', 'الكرك', 'الطفيلة', 'معان', 'العقبة');
    }

    public function available_schools() {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_core_student_years';
        return $wpdb->get_results("SELECT school_id, MAX(school_name) AS school_name FROM {$table} WHERE school_id IS NOT NULL AND school_id <> '' GROUP BY school_id ORDER BY school_name", ARRAY_A) ?: array();
    }

    public function find($school_id, $study_year) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE school_id = %s AND study_year = %s', $school_id, $study_year), ARRAY_A);
    }

    public static function validate_profile(array $input) {
        $profile = array();
        foreach (self::fields() as $group) foreach ($group as $key => $spec) {
            $value = isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
            $value = sanitize_text_field($value);
            if (strlen($value) > 500) return new WP_Error('emis_length', $spec[0] . ': قيمة طويلة جداً.');
            if ($spec[2] === 'governorate' && $value !== '' && !in_array($value, self::governorates(), true)) return new WP_Error('emis_governorate', 'المحافظة غير معروفة.');
            if ($spec[2] === 'school_gender' && $value !== '' && !in_array($value, array('ذكور', 'إناث', 'مختلط'), true)) return new WP_Error('emis_gender', 'جنس المدرسة غير صحيح.');
            if ($spec[2] === 'email' && $value !== '' && !is_email($value)) return new WP_Error('emis_email', 'البريد الإلكتروني غير صحيح.');
            if ($spec[2] === 'url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) return new WP_Error('emis_url', 'الموقع الإلكتروني غير صحيح.');
            if ($spec[2] === 'year' && $value !== '' && (!ctype_digit($value) || (int) $value < 1800 || (int) $value > (int) gmdate('Y') + 1)) return new WP_Error('emis_founded', 'سنة التأسيس غير صحيحة.');
            if ($spec[2] === 'coordinate' && $value !== '' && (!is_numeric($value) || (float) $value < ($key === 'latitude' ? -90 : -180) || (float) $value > ($key === 'latitude' ? 90 : 180))) return new WP_Error('emis_coordinate', 'الإحداثيات غير صحيحة.');
            if (in_array($spec[2], array('number', 'integer'), true) && $value !== '' && (!is_numeric($value) || (float) $value < 0 || ($spec[2] === 'integer' && !ctype_digit($value)))) return new WP_Error('emis_number', $spec[0] . ': أدخل رقماً غير سالب.');
            $profile[$key] = $value;
        }
        if ($profile['school_national_id'] !== '' && !ctype_digit($profile['school_national_id'])) return new WP_Error('emis_school_id', 'الرقم الوطني للمدرسة يجب أن يتكون من أرقام.');
        return $profile;
    }

    public function save(array $input) {
        global $wpdb;
        $school_id = isset($input['school_id']) ? sanitize_text_field((string) $input['school_id']) : '';
        $year = isset($input['study_year']) ? str_replace('/', '-', sanitize_text_field((string) $input['study_year'])) : '';
        if (!preg_match('/^\d{4}-\d{4}$/', $year) || (int) substr($year, 5, 4) !== (int) substr($year, 0, 4) + 1) return new WP_Error('emis_year', 'العام الدراسي غير صحيح.');
        $schools = $this->available_schools();
        $core_name = '';
        foreach ($schools as $school) if ((string) $school['school_id'] === $school_id) $core_name = (string) $school['school_name'];
        if ($core_name === '') return new WP_Error('emis_school', 'المدرسة غير موجودة في OLAMA Core.');
        $profile = self::validate_profile($input);
        if (is_wp_error($profile)) return $profile;
        if ($profile['school_name'] === '') $profile['school_name'] = $core_name;
        $existing = $this->find($school_id, $year);
        $revision = isset($input['revision']) ? (int) $input['revision'] : 0;
        if ($existing && $revision !== (int) $existing['revision']) return new WP_Error('emis_conflict', 'عُدّلت البيانات في جلسة أخرى. أعد تحميل الصفحة قبل الحفظ.');
        if (!$existing && $revision !== 0) return new WP_Error('emis_conflict', 'لم يعد هذا السجل متاحاً. أعد تحميل الصفحة.');
        $now = current_time('mysql');
        $data = array('school_id' => $school_id, 'study_year' => $year, 'school_name' => $profile['school_name'], 'school_number' => $profile['school_number'], 'school_national_id' => $profile['school_national_id'], 'profile_json' => wp_json_encode($profile, JSON_UNESCAPED_UNICODE), 'status' => 'draft', 'revision' => $revision + 1, 'updated_by' => get_current_user_id(), 'updated_at' => $now);
        if ($existing) $ok = $wpdb->update(self::table(), $data, array('id' => $existing['id'], 'revision' => $revision));
        else {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = $now;
            $ok = $wpdb->insert(self::table(), $data);
        }
        if ($ok === false || ($existing && $ok !== 1)) return new WP_Error('emis_save', 'تعذر حفظ السجل. أعد تحميل الصفحة وحاول مرة أخرى.');
        return true;
    }
}
