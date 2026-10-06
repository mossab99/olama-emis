<?php
if (!defined('ABSPATH')) exit;

/** Staff identity is stable; deployment and teaching load belong to a school/year. */
final class Olama_EMIS_Staff {
    public static function table($entity = 'staff') { global $wpdb; return $wpdb->prefix . 'olama_emis_' . $entity; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $audit = "revision bigint(20) unsigned NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL";
        $schemas = array(
            'staff' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                identity_number varchar(50) NOT NULL,
                identity_type varchar(20) NOT NULL DEFAULT 'national',
                first_name varchar(100) NOT NULL,
                father_name varchar(100) NOT NULL DEFAULT '',
                grandfather_name varchar(100) NOT NULL DEFAULT '',
                family_name varchar(100) NOT NULL,
                profile_json longtext NULL,
                highest_qualification_id bigint(20) unsigned NOT NULL DEFAULT 0,
                {$audit},
                PRIMARY KEY  (id),
                UNIQUE KEY identity_number (identity_number),
                KEY name_lookup (first_name,family_name)",
            'staff_assignments' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                staff_id bigint(20) unsigned NOT NULL,
                school_id varchar(50) NOT NULL,
                study_year varchar(20) NOT NULL,
                job_title varchar(100) NOT NULL DEFAULT '',
                profile_json longtext NULL,
                reported_periods int unsigned NULL,
                archived tinyint(1) NOT NULL DEFAULT 0,
                {$audit},
                PRIMARY KEY  (id),
                UNIQUE KEY staff_school_year (staff_id,school_id,study_year),
                KEY school_year (school_id,study_year)",
            'staff_qualifications' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                staff_id bigint(20) unsigned NOT NULL,
                degree varchar(100) NOT NULL,
                profile_json longtext NULL,
                {$audit},
                PRIMARY KEY  (id),
                KEY staff_lookup (staff_id)",
            'staff_workloads' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                assignment_id bigint(20) unsigned NOT NULL,
                subject varchar(100) NOT NULL,
                grade varchar(100) NOT NULL DEFAULT '',
                section varchar(100) NOT NULL DEFAULT '',
                weekly_periods int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY assignment_lookup (assignment_id)"
        );
        foreach ($schemas as $name => $schema) {
            $table = self::table($name);
            dbDelta("CREATE TABLE {$table} ({$schema}) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
            if ($wpdb->last_error) return false;
            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
            if (!$status || strtolower($status['Engine']) !== 'innodb') return false;
        }
        return true;
    }
    /** label, type, worksheet coordinate; blank coordinate denotes an extension. */
    public static function personal_fields() {
        return array(
            'identity_number'=>array('الرقم الوطني / الشخصي','digits','B4'),
            'identity_type'=>array('نوع الرقم التعريفي',array('national'=>'وطني','personal'=>'شخصي'),''),
            'civil_record'=>array('رقم القيد','text','C4'),
            'first_name'=>array('الاسم الأول','name','D4'), 'father_name'=>array('اسم الأب','name','E4'),
            'grandfather_name'=>array('اسم الجد','name','F4'), 'family_name'=>array('اسم العائلة','name','G4'),
            'birthplace'=>array('مكان الولادة','text','H4'), 'birth_date'=>array('تاريخ الميلاد','birth_date','I4'),
            'religion'=>array('الديانة','text','J4'), 'nationality'=>array('الجنسية','text','K4'),
            'gender'=>array('الجنس',array('ذكر'=>'ذكر','أنثى'=>'أنثى'),'L4'),
            'governorate'=>array('المحافظة','text','M5'), 'district'=>array('اللواء','text','N5'),
            'subdistrict'=>array('القضاء','text','O5'), 'city'=>array('المدينة','text','P5'),
            'neighborhood'=>array('الحي','text','Q5'), 'street'=>array('الشارع','text',''),
            'marital_status'=>array('الحالة الاجتماعية','text','V4'), 'mother_name'=>array('اسم الأم','text','W4'),
            'children_count'=>array('عدد الأولاد','integer','X4'), 'refugee_card'=>array('صفة بطاقة الغوث',array('لاجئ'=>'لاجئ','لا يحمل بطاقة'=>'لا يحمل بطاقة'),'AL5'),
            'phone'=>array('رقم الهاتف','tel','AM5'), 'disability'=>array('الإعاقة / الاحتياجات الخاصة','text',''),
            'languages'=>array('اللغات التي يتقنها','text','مؤهلات الكادر!M3'),
            'icdl'=>array('شهادة ICDL',array('نعم'=>'نعم','لا'=>'لا'),''),
            'teacher_training'=>array('شهادة تدريب المعلمين',array('نعم'=>'نعم','لا'=>'لا'),'')
        );
    }
    public static function assignment_fields() {
        return array(
            'job_title'=>array('وظيفة الكادر','name','Y4'), 'appointment_status'=>array('صفة التعيين','text','Z5'),
            'appointment_date'=>array('تاريخ العقد / كتاب التعيين','date','AA5'),
            'employment_basis'=>array('الوضع الوظيفي',array('دوام كامل'=>'دوام كامل','دوام جزئي'=>'دوام جزئي'),'AB5'),
            'current_status'=>array('الوضع الحالي','text','AF4'), 'interruption_years'=>array('سنوات الانقطاع','decimal','AG4'),
            'total_experience'=>array('سنوات الخبرة الكاملة','decimal','AH5'),
            'education_experience'=>array('سنوات الخبرة في التعليم / إدارته','decimal','AI5'),
            'gross_salary'=>array('إجمالي الراتب','decimal','AJ5'),
            'cadre_category'=>array('صفة الكادر',array('إداري'=>'إداري','تعليمي'=>'تعليمي'),'AK5'),
            'ministry_file_number'=>array('الرقم الوزاري / رقم الملف','text',''),
            'reported_periods'=>array('عدد الحصص الأسبوعية المصرح بها','integer','مؤهلات الكادر!L3'),
            'notes'=>array('ملاحظات','text','B24')
        );
    }
    public static function qualification_fields() {
        return array(
            'degree'=>array('أعلى مؤهل / المؤهل العلمي','name','G4'),
            'specialization'=>array('تخصص المؤهل','text','E4'),
            'bachelor_specialization'=>array('تخصص البكالوريوس للدراسات العليا','text','F4'),
            'graduation_date'=>array('تاريخ التخرج','date','H4'),
            'institution'=>array('الجامعة / المعهد / المدرسة','text','I4'),
            'graduation_country'=>array('بلد التخرج','text','J4'),
            'evaluation'=>array('التقدير','text','K3')
        );
    }
    public static function import_fields() {
        $fields = array();
        foreach (array_merge(self::personal_fields(),self::assignment_fields()) as $key=>$spec) $fields[$key]=$spec[0];
        return $fields;
    }
    public static function digits($value) {
        return strtr($value,array('٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9'));
    }
    public static function validate($input, $fields, $existing = array()) {
        $out = array();
        foreach ($fields as $key=>$spec) {
            $raw = array_key_exists($key,$input) ? $input[$key] : (isset($existing[$key]) ? $existing[$key] : '');
            if (!is_scalar($raw)) return new WP_Error('emis_staff_field',$spec[0] . ': قيمة غير صحيحة.');
            $value = sanitize_text_field(trim((string) $raw)); $type=$spec[1];
            if (mb_strlen($value) > ($type==='name' ? 100 : ($type==='digits' ? 50 : 500))) return new WP_Error('emis_staff_length',$spec[0] . ': النص طويل جداً.');
            if (in_array($type,array('digits','integer','decimal','date','birth_date'),true)) $value=self::digits($value);
            if ($value !== '') {
                if (is_array($type) && !array_key_exists($value,$type)) return new WP_Error('emis_staff_choice',$spec[0] . ': خيار غير صحيح.');
                if (in_array($type,array('integer','digits'),true) && !ctype_digit($value)) return new WP_Error('emis_staff_digits',$spec[0] . ': أدخل أرقاماً فقط.');
                if ($type==='integer' && (float)$value > 10000) return new WP_Error('emis_staff_integer',$spec[0] . ': الحد الأقصى 10000.');
                if ($type==='decimal' && (!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$value))) return new WP_Error('emis_staff_decimal',$spec[0] . ': أدخل رقماً غير سالب حتى منزلتين عشريتين.');
                if ($type==='date' || $type==='birth_date') {
                    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                    if (!$d || $d->format('Y-m-d')!==$value || $value>gmdate('Y-m-d') && $type==='birth_date') return new WP_Error('emis_staff_date',$spec[0] . ': تاريخ غير صحيح.');
                }
            }
            $out[$key]=$value;
        }
        return $out;
    }
    public function context($school,$year) {
        if (!is_scalar($school) || !is_scalar($year)) return new WP_Error('emis_staff_context','المدرسة أو العام غير صحيح.');
        $year=Olama_EMIS_Campus::year((string)$year);
        if (!$year || !in_array((string)$school,array_map('strval',array_column((new Olama_EMIS_Schools())->available_schools(),'school_id')),true)) return new WP_Error('emis_staff_context','المدرسة أو العام غير صحيح.');
        return array('school_id'=>(string)$school,'study_year'=>$year);
    }
    public function find($id, $lock=false) {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id=%d'.($lock?' FOR UPDATE':''),$id),ARRAY_A);
        if ($row) $row['profile']=array_merge((array)json_decode($row['profile_json'],true),array_intersect_key($row,self::personal_fields()));
        return $row;
    }
    public function by_identity($number) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table().' WHERE identity_number=%s',self::digits(trim($number))));
    }
    public function assignment($staff,$school,$year,$lock=false) {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('staff_assignments').' WHERE staff_id=%d AND school_id=%s AND study_year=%s'.($lock?' FOR UPDATE':''),$staff,$school,$year),ARRAY_A);
        if ($row) $row['profile']=array_merge((array)json_decode($row['profile_json'],true),array('job_title'=>$row['job_title'],'reported_periods'=>$row['reported_periods']===null?'':(string)$row['reported_periods']));
        return $row;
    }
    private function transaction($fn) {
        global $wpdb;
        if (false===$wpdb->query('START TRANSACTION')) return new WP_Error('emis_staff_database','تعذر بدء الحفظ.');
        try { $result=$fn(); if (false===$wpdb->query('COMMIT')) throw new RuntimeException('تعذر إتمام الحفظ.'); return $result; }
        catch (Exception $e) { $wpdb->query('ROLLBACK'); return new WP_Error('emis_staff_save',$e->getMessage()); }
    }
    private function write($table,$data,$id=0) {
        global $wpdb;
        $ok=$id ? $wpdb->update($table,$data,array('id'=>$id)) : $wpdb->insert($table,$data);
        if ($ok===false) throw new RuntimeException('تعذر حفظ السجل؛ تحقق من الرقم التعريفي المكرر.');
        return $id ?: (int)$wpdb->insert_id;
    }
    private function audit($revision,$new=false) {
        $data=array('revision'=>$revision+1,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql'));
        if ($new) $data+=array('created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'));
        return $data;
    }
    private function revision($row,$revision) {
        if ((int)$revision!==($row?(int)$row['revision']:0)) throw new RuntimeException('عُدّل السجل في جلسة أخرى. أعد تحميله قبل الحفظ.');
    }
    public function prepare_person($input,$existing=null,$assignment=null) {
        $p=self::validate($input,self::personal_fields(),$existing?$existing['profile']:array('identity_type'=>'national'));
        $a=self::validate($input,self::assignment_fields(),$assignment?$assignment['profile']:array());
        if (is_wp_error($p)) return $p; if (is_wp_error($a)) return $a;
        foreach (array('identity_number','first_name','family_name') as $key) if ($p[$key]==='') return new WP_Error('emis_staff_required','الرقم التعريفي والاسم الأول والعائلة مطلوبة.');
        if ($p['identity_type']==='') return new WP_Error('emis_staff_required','نوع الرقم التعريفي مطلوب.');
        if ($a['total_experience']!=='' && $a['education_experience']!=='' && (float)$a['total_experience']<(float)$a['education_experience']) return new WP_Error('emis_staff_experience','الخبرة الكاملة يجب أن تكون أكبر من أو تساوي الخبرة في التعليم.');
        return array($p,$a);
    }
    public function save_person($input) {
        $ctx=$this->context($input['school_id']??'',$input['study_year']??''); if (is_wp_error($ctx)) return $ctx;
        return $this->transaction(function() use($input,$ctx) {
            $id=absint($input['staff_id']??0); $staff=$id?$this->find($id,true):null;
            if ($id && !$staff) throw new RuntimeException('الموظف غير موجود.');
            $this->revision($staff,$input['staff_revision']??0);
            $assignment=$id?$this->assignment($id,$ctx['school_id'],$ctx['study_year'],true):null;
            $this->revision($assignment,$input['assignment_revision']??0);
            $prepared=$this->prepare_person($input,$staff,$assignment);
            if (is_wp_error($prepared)) throw new RuntimeException($prepared->get_error_message());
            list($p,$a)=$prepared;
            $duplicate=$this->by_identity($p['identity_number']);
            if ($duplicate && (int)$duplicate!==$id) throw new RuntimeException('الرقم التعريفي مستخدم لموظف آخر. استخدم البحث لإضافة قيده لهذه المدرسة.');
            $data=array_intersect_key($p,array_flip(array('identity_number','identity_type','first_name','father_name','grandfather_name','family_name')));
            $data['profile_json']=wp_json_encode($p,JSON_UNESCAPED_UNICODE); $data+=$this->audit($staff?$staff['revision']:0,!$staff);
            $id=$this->write(self::table(),$data,$id);
            $this->write(self::table('staff_assignments'),array_merge($ctx,array('staff_id'=>$id,'job_title'=>$a['job_title'],'profile_json'=>wp_json_encode($a,JSON_UNESCAPED_UNICODE),'reported_periods'=>$a['reported_periods']===''?null:(int)$a['reported_periods'],'archived'=>empty($input['archived'])?0:1),$this->audit($assignment?$assignment['revision']:0,!$assignment)),$assignment?$assignment['id']:0);
            return $id;
        });
    }
    public function qualifications($staff) { global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('staff_qualifications').' WHERE staff_id=%d ORDER BY id DESC',$staff),ARRAY_A)?:array(); }
    public function save_qualification($input) {
        return $this->transaction(function() use($input) {
            global $wpdb;
            $staff=$this->find(absint($input['staff_id']??0),true); if (!$staff) throw new RuntimeException('اختر موظفاً موجوداً.');
            $this->revision($staff,$input['staff_revision']??0);
            $id=absint($input['qualification_id']??0);
            $old=$id?$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('staff_qualifications').' WHERE id=%d AND staff_id=%d FOR UPDATE',$id,$staff['id']),ARRAY_A):null;
            if ($id && !$old) throw new RuntimeException('المؤهل لا يخص الموظف المحدد.');
            $this->revision($old,$input['qualification_revision']??0);
            $p=self::validate($input,self::qualification_fields(),$old?(array)json_decode($old['profile_json'],true):array());
            if (is_wp_error($p)) throw new RuntimeException($p->get_error_message());
            if ($p['degree']==='') throw new RuntimeException('المؤهل العلمي مطلوب.');
            $id=$this->write(self::table('staff_qualifications'),array_merge(array('staff_id'=>$staff['id'],'degree'=>$p['degree'],'profile_json'=>wp_json_encode($p,JSON_UNESCAPED_UNICODE)),$this->audit($old?$old['revision']:0,!$old)),$id);
            $highest=!empty($input['is_highest'])?$id:((int)$staff['highest_qualification_id']===$id?0:(int)$staff['highest_qualification_id']);
            $this->write(self::table(),array_merge(array('highest_qualification_id'=>$highest),$this->audit($staff['revision'])),$staff['id']);
            return $id;
        });
    }
    public function workloads($assignment) { global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('staff_workloads').' WHERE assignment_id=%d ORDER BY id',$assignment),ARRAY_A)?:array(); }
    public static function validate_workloads($rows) {
        if (!is_array($rows) || count($rows)>100) return new WP_Error('emis_staff_workloads','الحد الأقصى 100 توزيع.');
        $out=array(); $seen=array();
        foreach ($rows as $row) {
            if (!is_array($row)) return new WP_Error('emis_staff_workloads','توزيع غير صحيح.');
            $p=self::validate($row,array('subject'=>array('المبحث','name'),'grade'=>array('الصف','name'),'section'=>array('الشعبة','name'),'weekly_periods'=>array('الحصص','integer')));
            if (is_wp_error($p)) return $p;
            if (implode('',$p)==='') continue;
            if ($p['subject']==='' || $p['weekly_periods']==='') return new WP_Error('emis_staff_workloads','المبحث وعدد الحصص مطلوبان لكل توزيع.');
            $key=mb_strtolower($p['subject'].'|'.$p['grade'].'|'.$p['section']);
            if (isset($seen[$key])) return new WP_Error('emis_staff_workloads','توزيع مكرر لنفس المبحث والصف والشعبة.');
            $seen[$key]=true; $p['weekly_periods']=(int)$p['weekly_periods']; $out[]=$p;
        }
        if (array_sum(array_column($out,'weekly_periods'))>10000) return new WP_Error('emis_staff_workloads','مجموع الحصص يتجاوز 10000.');
        return $out;
    }
    public function save_workloads($input,$rows) {
        $ctx=$this->context($input['school_id']??'',$input['study_year']??''); if (is_wp_error($ctx)) return $ctx;
        $rows=self::validate_workloads($rows); if (is_wp_error($rows)) return $rows;
        $report=self::validate($input,array('reported_periods'=>self::assignment_fields()['reported_periods'])); if (is_wp_error($report)) return $report;
        return $this->transaction(function() use($input,$ctx,$rows,$report) {
            global $wpdb;
            $staff=$this->find(absint($input['staff_id']??0),true); if (!$staff) throw new RuntimeException('الموظف غير موجود.');
            $a=$this->assignment($staff['id'],$ctx['school_id'],$ctx['study_year'],true);
            if (!$a || $a['archived']) throw new RuntimeException('يلزم قيد فعال للموظف في المدرسة والعام المحددين.');
            $this->revision($a,$input['assignment_revision']??0);
            if (false===$wpdb->delete(self::table('staff_workloads'),array('assignment_id'=>$a['id']))) throw new RuntimeException('تعذر تحديث التوزيع.');
            foreach ($rows as $row) $this->write(self::table('staff_workloads'),array_merge($row,array('assignment_id'=>$a['id'])));
            $p=$a['profile']; $p['reported_periods']=$report['reported_periods'];
            $this->write(self::table('staff_assignments'),array_merge(array('reported_periods'=>$report['reported_periods']===''?null:(int)$report['reported_periods'],'profile_json'=>wp_json_encode($p,JSON_UNESCAPED_UNICODE)),$this->audit($a['revision'])),$a['id']);
            return true;
        });
    }
    public function load_summary($staff,$year) {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT a.id,a.school_id,a.reported_periods,a.job_title,COALESCE(SUM(w.weekly_periods),0) AS total FROM '.self::table('staff_assignments').' a LEFT JOIN '.self::table('staff_workloads').' w ON w.assignment_id=a.id WHERE a.staff_id=%d AND a.study_year=%s AND a.archived=0 GROUP BY a.id,a.school_id,a.reported_periods,a.job_title',$staff,$year),ARRAY_A)?:array();
        $limits=get_option('olama_emis_workload_limits',array('default'=>26));
        $warnings=array(); $total=0;
        foreach ($rows as $r) {
            $total+=(int)$r['total'];
            if ($r['reported_periods']!==null && (int)$r['reported_periods']!==(int)$r['total']) $warnings[]='مدرسة '.$r['school_id'].': الحصص المصرح بها '.$r['reported_periods'].'، والتوزيع '.$r['total'].'.';
            $limit=isset($limits[$r['job_title']])?$limits[$r['job_title']]:($limits['default']??null);
            if ($limit!==null && (int)$r['total']>(int)$limit) $warnings[]='مدرسة '.$r['school_id'].': تجاوز حد التنبيه الداخلي '.$limit.'.';
        }
        if (isset($limits['default']) && $total>(int)$limits['default'] && count(array_filter($rows,static function($r){return (int)$r['total']>0;}))>1) $warnings[]='إجمالي الحصص عبر المدارس '.$total.' يتجاوز حد التنبيه الداخلي.';
        return array('rows'=>$rows,'total'=>$total,'warnings'=>$warnings);
    }
    public function list_staff($ctx,$search='',$page=1,$size=25) {
        global $wpdb;
        $where=$wpdb->prepare('a.school_id=%s AND a.study_year=%s',$ctx['school_id'],$ctx['study_year']);
        if ($search!=='') { $like='%'.$wpdb->esc_like($search).'%'; $where.=$wpdb->prepare(" AND (s.identity_number LIKE %s OR CONCAT_WS(' ',s.first_name,s.father_name,s.grandfather_name,s.family_name) LIKE %s OR a.job_title LIKE %s)",$like,$like,$like); }
        $from=self::table().' s INNER JOIN '.self::table('staff_assignments').' a ON a.staff_id=s.id LEFT JOIN '.self::table('staff_qualifications').' q ON q.id=s.highest_qualification_id AND q.staff_id=s.id WHERE '.$where;
        $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.$from);
        $size=max(1,min(5001,(int)$size)); $page=max(1,(int)$page);
        $rows=$wpdb->get_results($wpdb->prepare('SELECT s.*,a.id AS assignment_id,a.profile_json AS assignment_json,a.job_title,a.archived,a.revision AS assignment_revision,a.reported_periods,q.degree AS highest_degree FROM '.$from.' ORDER BY s.first_name,s.family_name,s.id LIMIT %d OFFSET %d',$size,($page-1)*$size),ARRAY_A)?:array();
        return array('total'=>$total,'rows'=>$rows);
    }
    public function lookup($query) {
        global $wpdb;
        if (mb_strlen($query)<2) return array();
        $like='%'.$wpdb->esc_like(self::digits($query)).'%';
        return $wpdb->get_results($wpdb->prepare("SELECT id,identity_number,first_name,family_name FROM ".self::table()." WHERE identity_number LIKE %s OR CONCAT_WS(' ',first_name,father_name,grandfather_name,family_name) LIKE %s ORDER BY first_name,family_name LIMIT 20",$like,$like),ARRAY_A)?:array();
    }
    /** Preview captures revisions; confirmation revalidates each row against current data. */
    public function preview_import($rows,$ctx,$mode) {
        if (!in_array($mode,array('add','update'),true)) return new WP_Error('emis_staff_import','اختر إضافة أو تحديث.');
        $valid=$this->context($ctx['school_id']??'',$ctx['study_year']??''); if (is_wp_error($valid)) return $valid;
        $out=array();$seen=array();
        foreach ($rows as $i=>$row) {
            $number=self::digits(trim((string)($row['identity_number']??''))); $id=$this->by_identity($number);
            $staff=$id?$this->find($id):null; $a=$id?$this->assignment($id,$valid['school_id'],$valid['study_year']):null;
            $p=$this->prepare_person($row,$staff,$a); $error=is_wp_error($p)?$p->get_error_message():'';
            if ($mode==='add' && $id) $error='الرقم موجود؛ استخدم وضع التحديث أو البحث لإضافة قيد المدرسة.';
            if ($mode==='update' && (!$staff || !$a)) $error='لا يوجد قيد مطابق في هذه المدرسة والعام للتحديث.';
            if (isset($seen[$number])) $error='رقم تعريفي مكرر داخل الملف.';
            $seen[$number]=true;
            $out[]=array('line'=>$i+2,'input'=>array_merge($row,$valid,array('staff_id'=>$id?:0,'staff_revision'=>$staff?$staff['revision']:0,'assignment_revision'=>$a?$a['revision']:0,'archived'=>$a?$a['archived']:0)),'error'=>$error);
        }
        return $out;
    }
}
