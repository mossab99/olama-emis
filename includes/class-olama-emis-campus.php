<?php
if (!defined('ABSPATH')) exit;

/** Stable physical assets, with annual profiles and one use per room/year. */
final class Olama_EMIS_Campus {
    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'emis_' . $name;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $schemas = array(
            'buildings' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                school_id varchar(50) NOT NULL,
                building_number varchar(50) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY school_building (school_id,building_number)",
            'building_years' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                building_id bigint(20) unsigned NOT NULL,
                study_year varchar(20) NOT NULL,
                national_id varchar(50) NOT NULL DEFAULT '',
                profile_json longtext NULL,
                archived tinyint(1) NOT NULL DEFAULT 0,
                revision bigint(20) unsigned NOT NULL DEFAULT 1,
                updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY building_year (building_id,study_year)",
            'floors' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                building_id bigint(20) unsigned NOT NULL,
                floor_number int NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY building_floor (building_id,floor_number)",
            'floor_years' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                floor_id bigint(20) unsigned NOT NULL,
                study_year varchar(20) NOT NULL,
                floor_name varchar(100) NOT NULL DEFAULT '',
                area decimal(14,4) DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY floor_year (floor_id,study_year)",
            'rooms' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                building_id bigint(20) unsigned NOT NULL,
                room_number varchar(50) NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY building_room (building_id,room_number)",
            'room_years' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                room_id bigint(20) unsigned NOT NULL,
                study_year varchar(20) NOT NULL,
                floor_id bigint(20) unsigned NOT NULL,
                use_kind varchar(20) NOT NULL,
                profile_json longtext NULL,
                archived tinyint(1) NOT NULL DEFAULT 0,
                updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY room_year (room_id,study_year),
                KEY floor_lookup (floor_id,study_year)",
            'classrooms' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                room_year_id bigint(20) unsigned NOT NULL,
                profile_json longtext NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY classroom_year (room_year_id)",
            'facility_rooms' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                room_year_id bigint(20) unsigned NOT NULL,
                profile_json longtext NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY facility_year (room_year_id)",
        );
        foreach ($schemas as $name => $columns) {
            $table = self::table($name);
            dbDelta("CREATE TABLE {$table} (\n{$columns}\n) ENGINE=InnoDB {$charset};");
            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($table)), ARRAY_A);
            if (!$status || $status['Engine'] !== 'InnoDB') return false;
        }
        return true;
    }

    /** label, input kind, workbook label cell; blank cell = optional extension. */
    public static function building_fields() {
        return array(
            'national_id' => array('الرقم الوطني للبناء / السجل التجاري', 'digits', 'A4'),
            'area' => array('مساحة البناء م²', 'decimal', 'A6'),
            'constructed_year' => array('سنة الإنشاء', 'year', 'B6'),
            'construction_type' => array('نوعية البناء', 'text', 'C6'),
            'start_date' => array('تاريخ البدء', 'text', 'D6'),
            'condition' => array('حالة البنية التحتية / الحالة الإنشائية', 'text', 'E6'),
            'ownership' => array('الملكية', 'text', 'F6'),
            'acquisition_year' => array('سنة إنشاء / استملاك البناء', 'year', 'G6'),
            'building_model' => array('نموذج البناء', 'text', 'H6'),
            'future_expansion' => array('نمط التوسع المستقبلي', 'text', 'I6'),
            'basin_number' => array('رقم الحوض', 'digits', 'J6'),
            'plot_number' => array('رقم القطعة', 'digits', 'K6'),
            'plot_area' => array('مساحة القطعة م²', 'decimal', 'L6'),
            'landlord' => array('اسم مالك العقار المستأجر', 'text', 'M6'),
            'annual_rent' => array('قيمة الإيجار السنوي بالدينار', 'decimal', 'A9'),
            'electricity' => array('توفر الكهرباء', 'text', 'B9'),
            'heating_cooling' => array('التدفئة والتكييف', 'text', 'C9'),
            'accessible_passages' => array('ممرات ذوي الاحتياجات الخاصة', 'text', 'D9'),
            'water_supply' => array('شبكة المياه', 'text', 'E9'),
            'sewage_type' => array('نوع الصرف الصحي', 'text', 'F9'),
            'internal_maintenance' => array('الصيانة الداخلية', 'text', 'G9'),
            'external_maintenance' => array('الصيانة الخارجية', 'text', 'H9'),
            'drinking_facilities' => array('المشارب', 'text', 'I9'),
            'accessible_drinking_facilities' => array('المشارب لذوي الاحتياجات الخاصة', 'text', 'J9'),
            'water_quality' => array('جودة المياه', 'text', 'K9'),
            'water_sewage_network' => array('شبكة المياه والصرف الصحي', 'text', 'L9'),
            'floor_count' => array('عدد طوابق البناء', 'integer', 'M9'),
            'solar_panels' => array('الألواح الشمسية', 'text', ''),
            'emergency_exits' => array('عدد مخارج الطوارئ', 'integer', ''),
            'civil_defense_expiry' => array('انتهاء شهادة الدفاع المدني', 'date', ''),
            'notes' => array('ملاحظات', 'text', ''),
        );
    }

    public static function common_fields() {
        return array(
            'ownership' => array('ملكية الغرفة', array('ملك', 'مستأجر'), 'G10'),
            'length' => array('الطول م', 'decimal', ''),
            'width' => array('العرض م', 'decimal', ''),
            'area' => array('المساحة م²', 'decimal', 'H10 / I10'),
            'condition' => array('حالة البنية التحتية', 'text', 'I10 / K10'),
            'maintenance' => array('الصيانة اللازمة', 'text', 'J10 / L10'),
            'room_type' => array('نوع الغرفة', array('أساسية', 'مضافة'), 'K10 / M10'),
            'notes' => array('ملاحظات', 'text', 'M10 / N10'),
        );
    }

    public static function detail_fields($kind) {
        if ($kind === 'classroom') return array(
            'class_type' => array('نوع الصف', array('واحدة', 'مجمعة', 'دوارة'), 'C10'),
            'grade' => array('مستوى الصف', 'text', 'D10'),
            'section' => array('رمز الشعبة', 'text', 'E10'),
            'gender' => array('جنس الصف', array('ذكور', 'إناث', 'مختلط'), 'F10'),
            'combined_students' => array('عدد طلبة الشعب المجمعة', 'integer', 'M10 note'),
            'education_stream' => array('الفرع الثانوي', 'text', 'M10 note'),
            'ventilation' => array('التهوية والإضاءة', 'text', ''),
            'heating_cooling' => array('التدفئة والتبريد', 'text', ''),
        );
        return array(
            'facility_type' => array('نوع استخدام الغرفة', 'text', 'C10'),
            'lab_subtype' => array('نوع المختبر / المشغل إن وجد', 'text', 'E10'),
            'summary_category' => array('تصنيف الملخص', array('مختبر علوم', 'مختبر حاسوب', 'مختبر لغات', 'مكتبة', 'غرفة معلمين', 'دورات مياه', 'أخرى'), ''),
            'operational_status' => array('حالة التشغيل', array('مستغلة', 'غير مستغلة', 'تحتاج صيانة'), ''),
            'capacity' => array('السعة / عدد المقاعد', 'integer', ''),
            'stations' => array('عدد محطات العمل / الحواسيب', 'integer', ''),
            'sanitary_units' => array('عدد وحدات المراحيض', 'integer', ''),
        );
    }

    public static function year($value) {
        $value = str_replace('/', '-', (string) $value);
        return preg_match('/^\d{4}-\d{4}$/', $value) && (int) substr($value, 5) === (int) substr($value, 0, 4) + 1 ? $value : '';
    }

    public static function validate(array $input, array $fields) {
        $out = array();
        foreach ($fields as $key => $spec) {
            if (isset($input[$key]) && !is_scalar($input[$key])) return new WP_Error('emis_field', $spec[0] . ': قيمة غير صحيحة.');
            $value = isset($input[$key]) ? sanitize_text_field(trim((string) $input[$key])) : '';
            $value = strtr($value, array('٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'));
            if (mb_strlen($value) > 500) return new WP_Error('emis_length', $spec[0] . ': قيمة طويلة جداً.');
            $type = $spec[1];
            if ($value !== '') {
                if (is_array($type) && !in_array($value, $type, true)) return new WP_Error('emis_choice', $spec[0] . ': خيار غير صحيح.');
                if (in_array($type, array('digits','integer','year'), true) && !ctype_digit($value)) return new WP_Error('emis_integer', $spec[0] . ': أدخل أرقاماً فقط.');
                if ($type === 'integer' && (float) $value > 1000000) return new WP_Error('emis_integer', $spec[0] . ': الرقم كبير جداً.');
                if ($type === 'decimal' && (!preg_match('/^\d+(\.\d{1,4})?$/', $value) || (float) $value > 9999999999)) return new WP_Error('emis_decimal', $spec[0] . ': أدخل رقماً غير سالب (حتى أربع منازل عشرية).');
                if ($type === 'year' && ((int) $value < 1800 || (int) $value > (int) gmdate('Y') + 1)) return new WP_Error('emis_year', $spec[0] . ': سنة غير صحيحة.');
                if ($type === 'date') {
                    $date = DateTime::createFromFormat('!Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) return new WP_Error('emis_date', $spec[0] . ': تاريخ غير صحيح.');
                }
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private function fail($message) { throw new RuntimeException($message); }
    private function check($result) { if ($result === false) $this->fail('تعذر حفظ البيانات. لم تُحفظ أي تغييرات في الدفعة.'); return $result; }
    private function transaction($callback) {
        global $wpdb;
        try {
            $this->check($wpdb->query('START TRANSACTION'));
            $result = $callback();
            $this->check($wpdb->query('COMMIT'));
            return $result;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('emis_campus', $e->getMessage());
        }
    }

    public function buildings($school_id, $year) {
        global $wpdb;
        $b = self::table('buildings'); $y = self::table('building_years');
        return $wpdb->get_results($wpdb->prepare("SELECT b.*, y.id AS profile_id, y.national_id, y.profile_json, y.archived, y.revision FROM {$b} b LEFT JOIN {$y} y ON y.building_id=b.id AND y.study_year=%s WHERE b.school_id=%s ORDER BY b.building_number", $year, $school_id), ARRAY_A) ?: array();
    }

    public function building($id, $year) {
        global $wpdb;
        $b = self::table('buildings'); $y = self::table('building_years');
        return $wpdb->get_row($wpdb->prepare("SELECT b.*, y.id AS profile_id, y.national_id, y.profile_json, y.archived, y.revision FROM {$b} b LEFT JOIN {$y} y ON y.building_id=b.id AND y.study_year=%s WHERE b.id=%d", $year, $id), ARRAY_A);
    }

    private function scope(array $input, $allow_archived = false) {
        global $wpdb;
        $id = isset($input['building_id']) ? (int) $input['building_id'] : 0;
        $year = self::year(isset($input['study_year']) ? $input['study_year'] : '');
        if (!$year) $this->fail('العام الدراسي غير صحيح.');
        $b = self::table('buildings'); $y = self::table('building_years');
        $row = $wpdb->get_row($wpdb->prepare("SELECT y.*, b.school_id FROM {$y} y JOIN {$b} b ON b.id=y.building_id WHERE y.building_id=%d AND y.study_year=%s FOR UPDATE", $id, $year), ARRAY_A);
        if (!$row || (string) $row['school_id'] !== (string) $input['school_id']) $this->fail('اختر بناءً محفوظاً لهذه المدرسة والعام.');
        if ((int) $row['revision'] !== (int) $input['revision']) $this->fail('تغيرت بيانات البناء أو غرفه في جلسة أخرى. أعد تحميل الصفحة قبل الحفظ.');
        if (!$allow_archived && $row['archived']) $this->fail('البناء مؤرشف لهذا العام. أعد تفعيله قبل تعديل الغرف.');
        return $row;
    }

    private function touch($scope) {
        global $wpdb;
        $this->check($wpdb->update(self::table('building_years'), array('revision' => (int) $scope['revision'] + 1, 'updated_by' => get_current_user_id(), 'updated_at' => current_time('mysql')), array('id' => $scope['id'])));
    }

    public function save_building(array $input) {
        global $wpdb;
        $year = self::year(isset($input['study_year']) ? $input['study_year'] : '');
        $school_id = isset($input['school_id']) && is_scalar($input['school_id']) ? sanitize_text_field((string) $input['school_id']) : '';
        $schools = (new Olama_EMIS_Schools())->available_schools();
        if (!$year || !in_array($school_id, array_map('strval', array_column($schools, 'school_id')), true)) return new WP_Error('emis_school', 'المدرسة أو العام الدراسي غير صحيح.');
        $profile = self::validate($input, self::building_fields());
        if (is_wp_error($profile)) return $profile;
        $number = isset($input['building_number']) && is_scalar($input['building_number']) ? sanitize_text_field(trim((string) $input['building_number'])) : '';
        if ($number === '' || mb_strlen($number) > 50 || strlen($profile['national_id']) > 50) return new WP_Error('emis_number', 'رقم البناء مطلوب ويجب ألا يتجاوز 50 حرفاً.');
        return $this->transaction(function () use ($input, $profile, $number, $school_id, $year, $wpdb) {
            $id = isset($input['building_id']) ? (int) $input['building_id'] : 0;
            if (!$id) {
                $this->check($wpdb->insert(self::table('buildings'), array('school_id'=>$school_id,'building_number'=>$number,'created_at'=>current_time('mysql'))));
                $id = (int) $wpdb->insert_id;
                $existing = null;
            } else {
                $b = self::table('buildings');
                $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$b} WHERE id=%d FOR UPDATE", $id), ARRAY_A);
                if (!$asset || (string) $asset['school_id'] !== $school_id || $asset['building_number'] !== $number) $this->fail('هوية البناء غير صحيحة. رقم البناء ثابت للحفاظ على السجل التاريخي.');
                $y = self::table('building_years');
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$y} WHERE building_id=%d AND study_year=%s FOR UPDATE", $id, $year), ARRAY_A);
            }
            $revision = isset($input['revision']) ? (int) $input['revision'] : 0;
            if ($revision !== (int) ($existing ? $existing['revision'] : 0)) $this->fail('تغير سجل البناء. أعد تحميل الصفحة قبل الحفظ.');
            $data = array('building_id'=>$id,'study_year'=>$year,'national_id'=>$profile['national_id'],'profile_json'=>wp_json_encode($profile, JSON_UNESCAPED_UNICODE),'archived'=>empty($input['archived']) ? 0 : 1,'revision'=>$revision+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql'));
            if ($existing) $this->check($wpdb->update(self::table('building_years'), $data, array('id'=>$existing['id'])));
            else $this->check($wpdb->insert(self::table('building_years'), $data));
            return $id;
        });
    }

    public function floors($building_id, $year) {
        global $wpdb;
        $f = self::table('floors'); $y = self::table('floor_years');
        return $wpdb->get_results($wpdb->prepare("SELECT f.*, y.floor_name, y.area FROM {$f} f JOIN {$y} y ON y.floor_id=f.id AND y.study_year=%s WHERE f.building_id=%d ORDER BY f.floor_number", $year, $building_id), ARRAY_A) ?: array();
    }

    public function save_floor(array $input) {
        global $wpdb;
        if (!isset($input['floor_number']) || !preg_match('/^-?\d{1,3}$/', (string) $input['floor_number'])) return new WP_Error('emis_floor', 'رقم الطابق غير صحيح. الأرضي 0 والتسوية -1.');
        $profile = self::validate($input, array('floor_name'=>array('اسم الطابق','text',''),'area'=>array('مساحة الطابق','decimal','')));
        if (is_wp_error($profile)) return $profile;
        if ($profile['floor_name'] === '' || mb_strlen($profile['floor_name']) > 100) return new WP_Error('emis_floor', 'اسم الطابق مطلوب (حتى 100 حرف).');
        return $this->transaction(function () use ($input, $profile, $wpdb) {
            $scope = $this->scope($input);
            $f = self::table('floors');
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$f} WHERE building_id=%d AND floor_number=%d", $scope['building_id'], (int) $input['floor_number']));
            if (!$id) {
                $this->check($wpdb->insert($f, array('building_id'=>$scope['building_id'],'floor_number'=>(int) $input['floor_number'])));
                $id = $wpdb->insert_id;
            }
            $y = self::table('floor_years');
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$y} WHERE floor_id=%d AND study_year=%s", $id, $scope['study_year']));
            $data = array('floor_id'=>$id,'study_year'=>$scope['study_year'],'floor_name'=>$profile['floor_name'],'area'=>$profile['area'] === '' ? null : $profile['area']);
            if ($existing) $this->check($wpdb->update($y, $data, array('id'=>$existing)));
            else $this->check($wpdb->insert($y, $data));
            $this->touch($scope);
            return true;
        });
    }

    public function rooms($building_id, $year, $kind = '') {
        global $wpdb;
        $r = self::table('rooms'); $y = self::table('room_years');
        $c = self::table('classrooms'); $f = self::table('facility_rooms');
        $sql = "SELECT r.id AS room_id, r.room_number, y.*, c.profile_json AS classroom_json, f.profile_json AS facility_json FROM {$r} r JOIN {$y} y ON y.room_id=r.id LEFT JOIN {$c} c ON c.room_year_id=y.id LEFT JOIN {$f} f ON f.room_year_id=y.id WHERE r.building_id=%d AND y.study_year=%s";
        $args = array($building_id, $year);
        if ($kind !== '') { $sql .= ' AND y.use_kind=%s'; $args[] = $kind; }
        $sql .= ' ORDER BY r.room_number';
        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: array();
    }

    /** An omitted row is preserved; archive is explicit. Every posted row commits together. */
    public function save_rooms(array $input, array $rows, $source_kind) {
        global $wpdb;
        if (!in_array($source_kind, array('classroom','facility'), true) || count($rows) > 100) return new WP_Error('emis_batch', 'الدفعة غير صحيحة أو تتجاوز 100 غرفة.');
        $validated = array(); $numbers = array();
        foreach ($rows as $index => $row) {
            if (!is_array($row)) return new WP_Error('emis_batch', 'صف غير صحيح.');
            $number = isset($row['room_number']) && is_scalar($row['room_number']) ? sanitize_text_field(trim((string) $row['room_number'])) : '';
            if ($number === '' && empty($row['room_id'])) continue;
            if ($number === '' || mb_strlen($number) > 50) return new WP_Error('emis_room', 'رقم الغرفة مطلوب (حتى 50 حرفاً).');
            $key = mb_strtolower($number);
            if (isset($numbers[$key])) return new WP_Error('emis_duplicate', 'رقم غرفة مكرر في الدفعة: ' . $number);
            $numbers[$key] = true;
            $common = self::validate($row, self::common_fields());
            $details = self::validate($row, self::detail_fields($source_kind));
            if (is_wp_error($common)) return $common;
            if (is_wp_error($details)) return $details;
            $kind = isset($row['use_kind']) ? $row['use_kind'] : $source_kind;
            if (!in_array($kind, array('classroom','facility'), true)) return new WP_Error('emis_kind', 'نوع استخدام الغرفة غير صحيح.');
            if ($source_kind === 'facility' && $details['sanitary_units'] !== '' && $details['summary_category'] !== 'دورات مياه') return new WP_Error('emis_units', 'وحدات المراحيض تخص تصنيف دورات المياه فقط.');
            $validated[] = array('room_id'=>isset($row['room_id']) ? (int) $row['room_id'] : 0, 'number'=>$number, 'floor_id'=>isset($row['floor_id']) ? (int) $row['floor_id'] : 0,'kind'=>$kind,'common'=>$common,'details'=>$details,'archived'=>empty($row['archived']) ? 0 : 1);
        }
        return $this->transaction(function () use ($input, $validated, $source_kind, $wpdb) {
            $scope = $this->scope($input);
            $floors = array_map('intval', array_column($this->floors($scope['building_id'], $scope['study_year']), 'id'));
            foreach ($validated as $row) {
                if (!in_array($row['floor_id'], $floors, true)) $this->fail('الطابق لا يتبع البناء والعام المحددين.');
                $r = self::table('rooms');
                $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$r} WHERE building_id=%d AND room_number=%s", $scope['building_id'], $row['number']), ARRAY_A);
                if ($row['room_id']) {
                    if (!$asset || (int) $asset['id'] !== $row['room_id']) $this->fail('هوية الغرفة غير صحيحة. أرقام الغرف الحالية ثابتة.');
                } elseif (!$asset) {
                    $this->check($wpdb->insert($r, array('building_id'=>$scope['building_id'],'room_number'=>$row['number'])));
                    $asset = array('id'=>$wpdb->insert_id);
                }
                $y = self::table('room_years');
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$y} WHERE room_id=%d AND study_year=%s", $asset['id'], $scope['study_year']), ARRAY_A);
                if (!$row['room_id'] && $existing) $this->fail('الغرفة ' . $row['number'] . ' مسجلة بالفعل لهذا العام، بما فيها الغرف المؤرشفة وفي الوحدة الأخرى. افتح سجلها الحالي.');
                if ($row['room_id'] && (!$existing || $existing['use_kind'] !== $source_kind)) $this->fail('تغير استخدام الغرفة. أعد تحميل الصفحة.');
                $data = array('room_id'=>$asset['id'],'study_year'=>$scope['study_year'],'floor_id'=>$row['floor_id'],'use_kind'=>$row['kind'],'profile_json'=>wp_json_encode($row['common'], JSON_UNESCAPED_UNICODE),'archived'=>$row['archived'],'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql'));
                if ($existing) { $this->check($wpdb->update($y,$data,array('id'=>$existing['id']))); $year_id = $existing['id']; }
                else { $this->check($wpdb->insert($y,$data)); $year_id = $wpdb->insert_id; }
                $detail_table = self::table($source_kind === 'classroom' ? 'classrooms' : 'facility_rooms');
                $detail_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$detail_table} WHERE room_year_id=%d", $year_id));
                $detail_data = array('room_year_id'=>$year_id,'profile_json'=>wp_json_encode($row['details'], JSON_UNESCAPED_UNICODE));
                if ($detail_id) $this->check($wpdb->update($detail_table,$detail_data,array('id'=>$detail_id)));
                else $this->check($wpdb->insert($detail_table,$detail_data));
            }
            $this->touch($scope);
            return count($validated);
        });
    }

    public static function summarize(array $rooms) {
        $totals = array('classrooms'=>0,'facilities'=>0,'library_count'=>0,'science_labs'=>0,'computer_labs'=>0,'language_labs'=>0,'teacher_capacity'=>0,'sanitary_units'=>0,'uncategorized_facilities'=>0);
        $mapping = array('مختبر علوم'=>'science_labs','مختبر حاسوب'=>'computer_labs','مختبر لغات'=>'language_labs','مكتبة'=>'library_count');
        $unknown = array('teacher_capacity'=>false,'sanitary_units'=>false);
        foreach ($rooms as $room) {
            if (!empty($room['archived'])) continue;
            if ($room['use_kind'] === 'classroom') { $totals['classrooms']++; continue; }
            $totals['facilities']++;
            $detail = json_decode($room['facility_json'] ?: '{}', true) ?: array();
            $category = isset($detail['summary_category']) ? $detail['summary_category'] : '';
            if ($category === '') $totals['uncategorized_facilities']++;
            if (isset($mapping[$category])) $totals[$mapping[$category]]++;
            foreach (array('غرفة معلمين'=>array('teacher_capacity','capacity'),'دورات مياه'=>array('sanitary_units','sanitary_units')) as $label=>$pair) if ($category === $label) {
                if (!isset($detail[$pair[1]]) || $detail[$pair[1]] === '') $unknown[$pair[0]] = true;
                else $totals[$pair[0]] += (int) $detail[$pair[1]];
            }
        }
        foreach ($unknown as $key=>$missing) if ($missing) $totals[$key] = null;
        return $totals;
    }

    public function school_summary($school_id, $year) {
        $rooms = array(); $floor_total = 0; $floor_mismatches = array();
        foreach ($this->buildings($school_id, $year) as $building) {
            if (!$building['profile_id'] || $building['archived']) continue;
            $floors = $this->floors($building['id'], $year);
            $floor_total += count($floors);
            $profile = json_decode($building['profile_json'] ?: '{}', true) ?: array();
            if (isset($profile['floor_count']) && $profile['floor_count'] !== '' && (int) $profile['floor_count'] !== count($floors)) $floor_mismatches[] = $building['building_number'];
            $rooms = array_merge($rooms, $this->rooms($building['id'], $year));
        }
        $totals = self::summarize($rooms);
        $totals['floor_count'] = $floor_total;
        return array('totals'=>$totals,'floor_mismatches'=>$floor_mismatches);
    }
}
