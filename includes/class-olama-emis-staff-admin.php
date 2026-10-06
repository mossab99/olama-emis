<?php
if (!defined('ABSPATH')) exit;

final class Olama_EMIS_Staff_Admin {
    private $service;
    public function __construct() {
        $this->service=new Olama_EMIS_Staff();
        add_action('admin_menu',array($this,'menu'),33);
        add_action('admin_enqueue_scripts',array($this,'assets'));
        add_action('admin_post_olama_emis_staff_save',array($this,'save'));
        add_action('admin_post_olama_emis_staff_import',array($this,'import'));
        add_action('admin_post_olama_emis_staff_export',array($this,'export'));
        add_action('wp_ajax_olama_emis_staff_lookup',array($this,'lookup'));
        add_action('wp_ajax_olama_emis_staff_identity',array($this,'identity'));
    }
    public function menu() {
        foreach (array('staff'=>'بيانات الموظفين','qualifications'=>'المؤهلات وتوزيع الحصص') as $page=>$title) add_submenu_page('olama-emis',$title,$title,'olama_users_ministry_view','olama-emis-'.$page,array($this,'page'));
    }
    public function assets($hook) {
        if (strpos($hook,'olama-emis-staff')===false && strpos($hook,'olama-emis-qualifications')===false) return;
        wp_enqueue_script('olama-emis-staff',OLAMA_EMIS_URL.'assets/staff.js',array(),OLAMA_EMIS_VERSION,true);
        wp_localize_script('olama-emis-staff','OlamaEmisStaff',array('url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('olama_emis_staff_ajax')));
    }
    private function authorize($write=false) {
        if (!current_user_can($write?'olama_users_ministry_configure':'olama_users_ministry_view')) wp_die('Access denied.','',array('response'=>403));
        if ($write && get_option('olama_emis_schema_version')!==OLAMA_EMIS_VERSION) wp_die('EMIS database setup must complete before saving.');
    }
    private function args($source) {
        $out=array(); foreach (array('school_id','study_year','staff_id','s','page_size','paged') as $key) $out[$key]=isset($source[$key]) && is_scalar($source[$key])?sanitize_text_field((string)$source[$key]):'';
        $out['study_year']=$out['study_year']?:'2025-2026';
        $out['page']=($source['page']??'')==='olama-emis-qualifications'?'olama-emis-qualifications':'olama-emis-staff';
        return $out;
    }
    private function url($ctx,$extra=array()) { return add_query_arg(array_merge($ctx,$extra),admin_url('admin.php')); }
    private function key($type) { return 'olama_emis_staff_'.$type.'_'.get_current_user_id(); }
    private function redirect($ctx,$message) { wp_safe_redirect($this->url($ctx,array('emis_notice'=>$message))); exit; }
    private function hidden($data) { foreach ($data as $k=>$v) echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">'; }
    private function open_form($operation,$ctx,$extra=array()) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('olama_emis_staff_save'); $this->hidden(array_merge($ctx,$extra,array('action'=>'olama_emis_staff_save','operation'=>$operation)));
    }
    private function fields($fields,$values,$disabled=false) {
        echo '<div class="olama-emis__fields">';
        foreach ($fields as $key=>$spec) {
            $value=isset($values[$key]) && is_scalar($values[$key])?$values[$key]:''; $type=$spec[1];
            echo '<label><span>'.esc_html($spec[0]).($spec[2]===''?' <small class="olama-emis__optional">إضافي</small>':'').'</span>';
            if (is_array($type)) {
                echo '<select name="'.esc_attr($key).'">'.($key==='identity_type'?'':'<option value="">اختر</option>');
                foreach ($type as $v=>$label) echo '<option value="'.esc_attr($v).'" '.selected($value,$v,false).'>'.esc_html($label).'</option>';
                echo '</select>';
            } else {
                $htmltype=in_array($type,array('date','birth_date'),true)?'date':(in_array($type,array('integer','decimal'),true)?'number':($type==='tel'?'tel':'text'));
                $required=in_array($key,array('identity_number','first_name','family_name','degree'),true)?' required':'';
                $extra=$htmltype==='number'?' min="0" step="'.($type==='integer'?'1':'0.01').'"':'';
                if ($key==='degree') $extra.=' list="olama-emis-degree-choices"';
                echo '<input name="'.esc_attr($key).'" type="'.$htmltype.'" value="'.esc_attr($value).'" maxlength="'.($type==='name'?100:($type==='digits'?50:500)).'"'.$required.$extra.'>';
                if ($key==='identity_number') echo '<small role="status" class="olama-emis__identity-status"></small>';
            }
            echo '</label>';
        }
        echo '</div>';
    }
    public function page() {
        $this->authorize(); $ctx=$this->args(wp_unslash($_GET));
        $schools=(new Olama_EMIS_Schools())->available_schools(); if ($ctx['school_id']==='' && $schools) $ctx['school_id']=(string)$schools[0]['school_id'];
        $valid=$this->service->context($ctx['school_id'],$ctx['study_year']);
        $editable=current_user_can('olama_users_ministry_configure') && get_option('olama_emis_schema_version')===OLAMA_EMIS_VERSION;
        $qualification=$ctx['page']==='olama-emis-qualifications';
        echo '<div class="wrap olama-emis" dir="rtl"><header class="olama-emis__hero"><span>OLAMA EMIS · المرحلة '.($qualification?'٦':'٥').'</span><h1>'.($qualification?'المؤهلات وتوزيع الحصص':'البيانات الأساسية للموظفين').'</h1><p>سجل المعلمين والإداريين والفنيين · قيد مستقل لكل مدرسة وعام دراسي.</p></header>';
        if (isset($_GET['emis_notice']) && is_scalar($_GET['emis_notice'])) echo '<div class="notice notice-info"><p>'.esc_html(wp_unslash($_GET['emis_notice'])).'</p></div>';
        echo '<section class="olama-emis__panel"><form method="get" class="olama-emis__filters">'; $this->hidden(array('page'=>$ctx['page']));
        echo '<label>المدرسة<select name="school_id">'; foreach ($schools as $s) echo '<option value="'.esc_attr($s['school_id']).'" '.selected($ctx['school_id'],$s['school_id'],false).'>'.esc_html($s['school_name']).'</option>';
        echo '</select></label><label>العام الدراسي<input name="study_year" value="'.esc_attr($ctx['study_year']).'" required></label><label>بحث في سجل المدرسة<input name="s" value="'.esc_attr($ctx['s']).'" placeholder="الاسم / الرقم / الوظيفة"></label><label>عدد الصفوف<select name="page_size">';
        $size=$ctx['page_size']==='50'?50:25; foreach (array(25,50) as $n) echo '<option '.selected($size,$n,false).'>'.$n.'</option>';
        echo '</select></label><button class="button">عرض</button></form><nav class="olama-emis__tabs">';
        foreach (array('staff'=>'الموظفون','qualifications'=>'المؤهلات والحصص') as $p=>$label) echo '<a class="button" href="'.esc_url($this->url($ctx,array('page'=>'olama-emis-'.$p))).'">'.$label.'</a>';
        echo '</nav></section>';
        if (is_wp_error($valid)) { echo '<section class="olama-emis__panel">'.esc_html($valid->get_error_message()).'</section></div>'; return; }
        $ctx=array_merge($ctx,$valid);
        $paged=max(1,(int)$ctx['paged']); $list=$this->service->list_staff($ctx,$ctx['s'],$paged,$size);
        echo '<section class="olama-emis__panel"><div class="olama-emis__panel-head"><h2>سجل المدرسة · '.esc_html($list['total']).' موظف</h2>';
        if (!$qualification) { foreach (array('xlsx'=>'تصدير Excel','csv'=>'تصدير CSV') as $format=>$label) echo '<a class="button" href="'.esc_url(wp_nonce_url(add_query_arg(array_merge($ctx,array('action'=>'olama_emis_staff_export','format'=>$format)),admin_url('admin-post.php')),'olama_emis_staff_export')).'">'.$label.'</a>'; }
        echo '</div><div class="olama-emis__scroll"><table class="widefat striped"><thead><tr><th>الرقم الوطني / الشخصي</th><th>الاسم الكامل</th><th>الوظيفة</th><th>المؤهل الأعلى</th><th>القيد</th><th></th></tr></thead><tbody>';
        foreach ($list['rows'] as $row) {
            $degree=$row['highest_degree'];
            echo '<tr><td dir="ltr">'.esc_html($row['identity_number']).'</td><td>'.esc_html(implode(' ',array_filter(array($row['first_name'],$row['father_name'],$row['grandfather_name'],$row['family_name'])))).'</td><td>'.esc_html($row['job_title']).'</td><td>'.esc_html($degree?:'—').'</td><td>'.($row['archived']?'مؤرشف':'فعال').'</td><td><a class="button" href="'.esc_url($this->url($ctx,array('staff_id'=>$row['id'],'paged'=>$paged)).'#olama-emis-staff-editor').'">فتح الملف</a></td></tr>';
        }
        if (!$list['rows']) echo '<tr><td colspan="6">لا توجد سجلات مطابقة.</td></tr>';
        echo '</tbody></table></div><nav class="olama-emis__tabs">';
        if ($paged>1) echo '<a class="button" href="'.esc_url($this->url($ctx,array('paged'=>$paged-1))).'">السابق</a>';
        echo '<span>صفحة '.esc_html($paged).' / '.max(1,(int)ceil($list['total']/$size)).'</span>';
        if ($paged*$size<$list['total']) echo '<a class="button" href="'.esc_url($this->url($ctx,array('paged'=>$paged+1))).'">التالي</a>';
        echo '</nav></section>';
        echo '<section class="olama-emis__panel"><h2>اختيار موظف من السجل العام</h2><p>ابحث بالاسم أو الرقم لإضافة قيد مدرسة لموظف موجود أو لعرض مؤهلاته.</p><div class="olama-emis__lookup" data-context="'.esc_attr(wp_json_encode($ctx)).'"><label>بحث عن موظف<input class="olama-emis__lookup-input" autocomplete="off" placeholder="حرفان أو أكثر"></label><div role="status" class="olama-emis__lookup-results"></div></div><p><a class="button" href="'.esc_url($this->url($ctx,array('staff_id'=>0)).'#olama-emis-staff-editor').'">موظف جديد</a></p></section>';
        $staff=$this->service->find(absint($ctx['staff_id']));
        $a=$staff?$this->service->assignment($staff['id'],$ctx['school_id'],$ctx['study_year']):null;
        if (!empty($_GET['discard_failed']) && isset($_GET['_wpnonce']) && is_scalar($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'],'olama_emis_staff_discard')) delete_transient($this->key('failed'));
        $failed=get_transient($this->key('failed')); if ($failed && ($failed['ctx']['school_id']!==$ctx['school_id'] || $failed['ctx']['study_year']!==$ctx['study_year'] || $failed['ctx']['page']!==$ctx['page'] || (string)$failed['ctx']['staff_id']!==(string)$ctx['staff_id'])) $failed=null;
        echo '<div id="olama-emis-staff-editor">';
        if ($failed) echo '<p class="olama-emis__mismatch">تظهر القيم التي لم تُحفظ. <a href="'.esc_url(wp_nonce_url($this->url($ctx,array('discard_failed'=>1)).'#olama-emis-staff-editor','olama_emis_staff_discard')).'">إعادة تحميل البيانات المحفوظة</a></p>';
        if ($qualification) {
            if ($staff) $this->qualifications_page($ctx,$staff,$a,$editable,$failed);
            else echo '<section class="olama-emis__panel"><p>اختر موظفاً لعرض المؤهلات والحصص.</p></section>';
        } else {
            $p=$staff?$staff['profile']:array('identity_type'=>'national'); $job=$a?$a['profile']:array();
            $revisions=array('staff_revision'=>$staff?$staff['revision']:0,'assignment_revision'=>$a?$a['revision']:0);
            if ($failed && $failed['operation']==='person') { $p=array_merge($p,$failed['input']); $job=array_merge($job,$failed['input']); $revisions=array_intersect_key($failed['input'],$revisions); }
            $this->open_form('person',$ctx,$revisions);
            echo '<fieldset '.(!$editable?'disabled':'').'><section class="olama-emis__panel"><h2>'.($staff?'ملف الموظف':'إضافة موظف').'</h2><p>تعديل البيانات الشخصية يظهر في جميع قيود الموظف. الحقول الإضافية مميزة؛ تُحفظ البيانات كمسودة.</p>';
            $this->fields(Olama_EMIS_Staff::personal_fields(),$p); echo '</section><section class="olama-emis__panel"><h2>قيد المدرسة والعام الدراسي</h2>';
            $this->fields(Olama_EMIS_Staff::assignment_fields(),$job);
            echo '<p><label class="olama-emis__check"><input type="checkbox" name="archived" value="1" '.checked($failed&&$failed['operation']==='person'?!empty($failed['input']['archived']):($a&&!empty($a['archived'])),true,false).'>أرشفة القيد لهذا العام</label></p></section>';
            if ($editable) echo '<p class="olama-emis__save"><button class="button button-primary">حفظ ملف الموظف وقيده</button></p>';
            echo '</fieldset></form>';
            $this->import_panel($ctx,$editable);
        }
        echo '</div></div>';
    }
    private function qualifications_page($ctx,$staff,$a,$editable,$failed) {
        echo '<section class="olama-emis__panel"><h2>'.esc_html($staff['first_name'].' '.$staff['family_name']).'</h2><p dir="ltr">'.esc_html($staff['identity_number']).'</p><p>اللغات: '.esc_html($staff['profile']['languages']??'—').' · ICDL: '.esc_html($staff['profile']['icdl']??'—').' · تدريب المعلمين: '.esc_html($staff['profile']['teacher_training']??'—').'</p></section>';
        echo '<datalist id="olama-emis-degree-choices">'; foreach (array('ثانوية عامة','دبلوم','بكالوريوس','دبلوم عالي','ماجستير','دكتوراه') as $degree) echo '<option value="'.esc_attr($degree).'">'; echo '</datalist>';
        $qs=$this->service->qualifications($staff['id']); $qs[]=array('id'=>0,'revision'=>0,'profile_json'=>'{}');
        foreach ($qs as $q) {
            $p=(array)json_decode($q['profile_json'],true); $rev=$staff['revision']; $qrev=$q['revision']; $highest=(int)$staff['highest_qualification_id']===(int)$q['id'] && $q['id'];
            if ($failed && $failed['operation']==='qualification' && (int)($failed['input']['qualification_id']??0)===(int)$q['id']) { $p=$failed['input']; $rev=$p['staff_revision']; $qrev=$p['qualification_revision']; $highest=!empty($p['is_highest']); }
            $this->open_form('qualification',$ctx,array('staff_revision'=>$rev,'qualification_id'=>$q['id'],'qualification_revision'=>$qrev));
            echo '<section class="olama-emis__panel"><h2>'.($q['id']?'مؤهل مسجل':'إضافة مؤهل').'</h2><fieldset '.(!$editable?'disabled':'').'>';
            $this->fields(Olama_EMIS_Staff::qualification_fields(),$p);
            echo '<p><label class="olama-emis__check"><input type="checkbox" name="is_highest" value="1" '.checked($highest,true,false).'>المؤهل الأعلى المستخدم في التقرير</label></p>';
            if ($editable) echo '<button class="button button-primary">حفظ المؤهل</button>';
            echo '</fieldset></section></form>';
        }
        if (!$a) { echo '<section class="olama-emis__panel"><p>أضف قيد المدرسة من صفحة الموظفين قبل توزيع الحصص.</p></section>'; return; }
        $summary=$this->service->load_summary($staff['id'],$ctx['study_year']); $rows=$this->service->workloads($a['id']); $report=$a['profile']['reported_periods']; $rev=$a['revision'];
        if ($failed && $failed['operation']==='workloads') { $decoded=json_decode($failed['input']['workloads_json']??'[]',true); $rows=is_array($decoded)?array_slice(array_filter($decoded,'is_array'),0,100):array(); $report=$failed['input']['reported_periods']??''; $rev=$failed['input']['assignment_revision']; }
        $this->open_form('workloads',$ctx,array('assignment_revision'=>$rev));
        echo '<section class="olama-emis__panel"><h2>توزيع الحصص · <bdi dir="ltr">'.esc_html($ctx['study_year']).'</bdi></h2><p>إجمالي الحصص عبر المدارس: <strong>'.esc_html($summary['total']).'</strong>. حد التنبيه الداخلي قابل للتعديل ولا يمثل اعتماداً لسياسة الوزارة.</p>';
        foreach ($summary['warnings'] as $w) echo '<p class="olama-emis__mismatch">'.esc_html($w).'</p>';
        echo '<fieldset '.(!$editable || $a['archived']?'disabled':'').'><div class="olama-emis__fields">';
        echo '<label>الحصص الأسبوعية المصرح بها<input type="number" min="0" max="10000" name="reported_periods" value="'.esc_attr($report).'"></label><div><strong class="olama-emis__workload-total">'.array_sum(array_column($rows,'weekly_periods')).'</strong> حصة موزعة</div></div><input type="hidden" name="workloads_json" value=""><div role="alert" class="olama-emis__grid-error"></div><div class="olama-emis__scroll"><table class="widefat olama-emis__workload-grid"><thead><tr><th>المبحث</th><th>الصف</th><th>الشعبة</th><th>الحصص الأسبوعية</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) $this->workload_row($row,$editable&&!$a['archived']);
        echo '</tbody></table></div><template class="olama-emis__workload-template">'; $this->workload_row(array(),true); echo '</template>';
        if ($editable && !$a['archived']) echo '<p><button type="button" class="button olama-emis__add-workload">إضافة توزيع</button> <button class="button button-primary">حفظ جميع توزيعات هذا القيد</button></p>';
        if ($a['archived']) echo '<p>القيد مؤرشف؛ أعد تفعيله من صفحة الموظفين قبل تعديل الحصص.</p>';
        echo '<noscript>يلزم JavaScript لحفظ توزيع الحصص.</noscript></fieldset></section></form>';
        $this->open_form('limits',$ctx); $limits=get_option('olama_emis_workload_limits',array('default'=>26));
        echo '<section class="olama-emis__panel"><h2>حدود التنبيه الداخلية</h2><p>حد افتراضي مع استثناءات حسب المسمى الوظيفي؛ لا تمنع الحفظ.</p><fieldset '.(!$editable?'disabled':'').'><label>الحد الافتراضي<input type="number" min="0" max="10000" name="default_limit" value="'.esc_attr($limits['default']??26).'"></label><label>استثناءات الوظائف: وظيفة=حد، كل استثناء في سطر<textarea name="role_limits" rows="4">';
        $lines=array();foreach ($limits as $role=>$n) if ($role!=='default') $lines[]=$role.'='.$n;
        echo esc_textarea(implode("\n",$lines)).'</textarea></label>';
        if ($editable) echo '<p><button class="button">حفظ حدود التنبيه</button></p>';
        echo '</fieldset></section></form>';
    }
    private function workload_row($row,$editable) {
        echo '<tr>'; foreach (array('subject','grade','section','weekly_periods') as $key) echo '<td><input aria-label="'.esc_attr(array('subject'=>'المبحث','grade'=>'الصف','section'=>'الشعبة','weekly_periods'=>'الحصص الأسبوعية')[$key]).'" data-key="'.$key.'" type="'.($key==='weekly_periods'?'number':'text').'" '.($key==='weekly_periods'?'min="0" max="10000"':'maxlength="100"').' value="'.esc_attr(isset($row[$key]) && is_scalar($row[$key])?$row[$key]:'').'"></td>';
        echo '<td>'.($editable?'<button type="button" class="button-link-delete olama-emis__remove-workload">إزالة التوزيع</button>':'').'</td></tr>';
    }
    public function save() {
        $this->authorize(true); check_admin_referer('olama_emis_staff_save'); $input=wp_unslash($_POST); $ctx=$this->args($input); $operation=$input['operation']??'';
        if ($operation==='person') $result=$this->service->save_person($input);
        elseif ($operation==='qualification') $result=$this->service->save_qualification($input);
        elseif ($operation==='workloads') { $raw=$input['workloads_json']??''; $rows=is_string($raw)&&strlen($raw)<=200000?json_decode($raw,true):null; $result=$this->service->save_workloads($input,$rows); }
        elseif ($operation==='limits') {
            $limits=array('default'=>Olama_EMIS_Staff::digits((string)($input['default_limit']??''))); $result=true;
            foreach (explode("\n",(string)($input['role_limits']??'')) as $line) { if (trim($line)==='') continue; $parts=explode('=',trim($line),2); if(count($parts)!==2 || trim($parts[0])==='') {$result=new WP_Error('emis_limits','استخدم وظيفة=حد.'); break;} $limits[sanitize_text_field(trim($parts[0]))]=Olama_EMIS_Staff::digits(trim($parts[1])); }
            foreach ($limits as $limit) if (!ctype_digit((string)$limit) || (int)$limit>10000) $result=new WP_Error('emis_limits','حد التنبيه من 0 إلى 10000.');
            if (!is_wp_error($result)) update_option('olama_emis_workload_limits',array_map('intval',$limits),false);
        } else $result=new WP_Error('emis_request','طلب غير صحيح.');
        if (is_wp_error($result)) { set_transient($this->key('failed'),array('ctx'=>$ctx,'input'=>$input,'operation'=>$operation),20*MINUTE_IN_SECONDS); $this->redirect($ctx,$result->get_error_message()); }
        delete_transient($this->key('failed')); if ($operation==='person') $ctx['staff_id']=$result;
        $this->redirect($ctx,'حُفظ السجل بنجاح.');
    }
    public function lookup() {
        $this->authorize(); check_ajax_referer('olama_emis_staff_ajax','nonce'); $query=isset($_GET['q'])&&is_scalar($_GET['q'])?sanitize_text_field(wp_unslash($_GET['q'])):'';
        wp_send_json_success($this->service->lookup($query));
    }
    public function identity() {
        $this->authorize(); check_ajax_referer('olama_emis_staff_ajax','nonce');
        $number=isset($_GET['number'])&&is_scalar($_GET['number'])?Olama_EMIS_Staff::digits(trim(wp_unslash($_GET['number']))):'';
        if ($number==='' || !ctype_digit($number) || strlen($number)>50) wp_send_json_error(array('message'=>'أدخل رقماً تعريفياً صحيحاً حتى 50 رقماً.'));
        $id=$this->service->by_identity($number); wp_send_json_success(array('staff_id'=>$id?:0,'message'=>$id?'الرقم مسجل بالفعل.':'صيغة صحيحة؛ لا يوجد رقم مكرر في السجل.'));
    }
    private function import_panel($ctx,$editable) {
        if (!$editable) return;
        echo '<section class="olama-emis__panel"><h2>استيراد دفعة Excel / CSV</h2><p>حتى 2000 صف و5 MB. معاينة ثم تأكيد. الإضافة تنشئ موظفين جدد؛ التحديث يطابق الرقم مع قيد المدرسة والعام. الأعمدة الغائبة تبقى كما هي، والقيم الفارغة في الأعمدة الموجودة تمسح الحقل. التواريخ YYYY-MM-DD؛ احفظ الأرقام التعريفية كنص.</p><p>';
        foreach (array('xlsx'=>'قالب Excel','csv'=>'قالب CSV') as $format=>$label) echo '<a class="button" href="'.esc_url(wp_nonce_url(add_query_arg(array_merge($ctx,array('action'=>'olama_emis_staff_export','template'=>1,'format'=>$format)),admin_url('admin-post.php')),'olama_emis_staff_export')).'">'.$label.'</a> ';
        echo '</p><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('olama_emis_staff_import'); $this->hidden(array_merge($ctx,array('action'=>'olama_emis_staff_import','step'=>'preview')));
        echo '<div class="olama-emis__filters"><label>الوضع<select name="mode"><option value="add">إضافة موظفين جدد</option><option value="update">تحديث القيود الموجودة</option></select></label><label>الملف<input type="file" name="staff_file" accept=".xlsx,.csv" required></label><button class="button">معاينة الملف</button></div></form>';
        $preview=get_transient($this->key('preview'));
        if ($preview && $preview['ctx']['school_id']===$ctx['school_id'] && $preview['ctx']['study_year']===$ctx['study_year']) {
            $errors=array_filter($preview['rows'],static function($r){return $r['error']!=='';});
            echo '<h3>معاينة '.count($preview['rows']).' صف · '.count($errors).' خطأ</h3><p>الوضع: '.($preview['mode']==='add'?'إضافة':'تحديث').'. تعرض المعاينة أول 50 صفاً وكل صفوف الأخطاء.</p><div class="olama-emis__scroll"><table class="widefat"><thead><tr><th>صف الملف</th><th>الرقم</th><th>الاسم</th><th>النتيجة</th></tr></thead><tbody>';
            foreach ($preview['rows'] as $i=>$r) if ($i<50 || $r['error']!=='') echo '<tr><td>'.esc_html($r['line']).'</td><td>'.esc_html($r['input']['identity_number']??'').'</td><td>'.esc_html(($r['input']['first_name']??'').' '.($r['input']['family_name']??'')).'</td><td>'.esc_html($r['error']?:'جاهز').'</td></tr>';
            echo '</tbody></table></div>';
            if (!$errors && $preview['rows']) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('olama_emis_staff_import');$this->hidden(array_merge($ctx,array('action'=>'olama_emis_staff_import','step'=>'apply','token'=>$preview['token'])));echo '<p><button class="button button-primary">تأكيد استيراد '.count($preview['rows']).' صف</button></p></form>'; }
        }
        $report=get_transient($this->key('report')); if ($report && $report['ctx']['school_id']===$ctx['school_id'] && $report['ctx']['study_year']===$ctx['study_year']) { echo '<h3>نتيجة الاستيراد: حُفظ '.esc_html($report['saved']).'، فشل '.count($report['errors']).'</h3>';foreach ($report['errors'] as $err) echo '<p class="olama-emis__mismatch">'.esc_html($err).'</p>'; }
        echo '</section>';
    }
    public function import() {
        $this->authorize(true);check_admin_referer('olama_emis_staff_import');$input=wp_unslash($_POST);$ctx=$this->args($input);
        if (($input['step']??'')==='preview') {
            delete_transient($this->key('preview'));
            $file=$_FILES['staff_file']??null;
            if (!$file || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) $this->redirect($ctx,'تعذر تحميل الملف.');
            $rows=Olama_EMIS_Import::rows($file['tmp_name'],$file['name'],Olama_EMIS_Staff::import_fields(),'identity_number',array('birth_date','appointment_date'));
            if (is_wp_error($rows)) $this->redirect($ctx,$rows->get_error_message());
            $preview=$this->service->preview_import($rows,$ctx,$input['mode']??'');if(is_wp_error($preview)) $this->redirect($ctx,$preview->get_error_message());
            set_transient($this->key('preview'),array('ctx'=>$ctx,'mode'=>$input['mode'],'rows'=>$preview,'token'=>wp_generate_password(32,false)),10*MINUTE_IN_SECONDS);$this->redirect($ctx,'راجع المعاينة قبل تأكيد الاستيراد.');
        }
        if (($input['step']??'')!=='apply') $this->redirect($ctx,'طلب غير صحيح.');
        $p=get_transient($this->key('preview'));
        if (!$p || !is_string($input['token']??null) || !hash_equals($p['token'],$input['token']) || $p['ctx']['school_id']!==$ctx['school_id'] || $p['ctx']['study_year']!==$ctx['study_year']) $this->redirect($ctx,'انتهت المعاينة؛ ارفع الملف مرة أخرى.');
        foreach ($p['rows'] as $row) if ($row['error']!=='') $this->redirect($ctx,'صحح الأخطاء وأعد المعاينة.');
        delete_transient($this->key('preview')); $saved=0;$errors=array();
        foreach ($p['rows'] as $row) { $r=$this->service->save_person($row['input']);if(is_wp_error($r))$errors[]='صف '.$row['line'].': '.$r->get_error_message();else $saved++; }
        set_transient($this->key('report'),array('ctx'=>$ctx,'saved'=>$saved,'errors'=>$errors),20*MINUTE_IN_SECONDS);$this->redirect($ctx,'انتهى الاستيراد. راجع عدد السجلات المحفوظة والأخطاء.');
    }
    public function export() {
        $this->authorize();check_admin_referer('olama_emis_staff_export');$ctx=$this->args(wp_unslash($_GET));$valid=$this->service->context($ctx['school_id'],$ctx['study_year']);if(is_wp_error($valid))wp_die(esc_html($valid->get_error_message()));
        $template=!empty($_GET['template']);$headers=array_keys(Olama_EMIS_Staff::import_fields());$rows=array();
        if (!$template) {
            $list=$this->service->list_staff($valid,$ctx['s'],1,5001);if($list['total']>5000)wp_die('Export up to 5000 matching records; narrow your search.');
            foreach($list['rows'] as $row){$p=array_merge((array)json_decode($row['profile_json'],true),array_intersect_key($row,Olama_EMIS_Staff::personal_fields()),(array)json_decode($row['assignment_json'],true));$values=array();foreach($headers as $k)$values[]=$p[$k]??'';$rows[]=$values;}
        }
        $format=($_GET['format']??'xlsx')==='csv'?'csv':'xlsx'; $name=$template?'olama-emis-staff-template':'olama-emis-staff-'.str_replace('-','',$valid['study_year']);
        $path=$format==='xlsx'?Olama_EMIS_Staff_Export::xlsx($headers,$rows):null;if(is_wp_error($path))wp_die(esc_html($path->get_error_message()));
        nocache_headers();header('Content-Type: '.($format==='csv'?'text/csv; charset=utf-8':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));header('Content-Disposition: attachment; filename="'.$name.'.'.$format.'"');
        if($format==='xlsx'){readfile($path);unlink($path);}else{$h=fopen('php://output','w');fwrite($h,"\xEF\xBB\xBF");foreach(array_merge(array($headers),$rows)as$r)fputcsv($h,array_map(array('Olama_EMIS_Staff_Export','csv_value'),$r),',','"','\\');fclose($h);}exit;
    }
}
