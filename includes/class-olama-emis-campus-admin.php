<?php
if (!defined('ABSPATH')) exit;

final class Olama_EMIS_Campus_Admin {
    private $service;
    private $pages = array('olama-emis-buildings'=>'البيانات الأساسية للبناء','olama-emis-classrooms'=>'بيانات الغرف الصفية','olama-emis-facilities'=>'غرف غير صفية');

    public function __construct() {
        $this->service = new Olama_EMIS_Campus();
        add_action('admin_menu', array($this,'menu'), 32);
        add_action('admin_post_olama_emis_save_campus', array($this,'save'));
        add_action('admin_enqueue_scripts', array($this,'assets'));
    }
    public function menu() {
        foreach ($this->pages as $slug=>$title) add_submenu_page('olama-emis',$title,$title,'olama_users_ministry_view',$slug,array($this,'page'));
    }
    public function assets($hook) {
        if (strpos($hook,'olama-emis') !== false) wp_enqueue_script('olama-emis-campus',OLAMA_EMIS_URL . 'assets/campus.js',array(),OLAMA_EMIS_VERSION,true);
    }
    private function url($page,$school,$year,$building=0,$room_page=1) {
        return add_query_arg(array('page'=>$page,'school_id'=>$school,'study_year'=>$year,'building_id'=>$building,'room_page'=>$room_page),admin_url('admin.php'));
    }
    private function token($page,$school,$year,$building) {
        return 'emis_campus_' . get_current_user_id() . '_' . md5($page . '|' . $school . '|' . $year . '|' . $building);
    }
    public function save() {
        if (!current_user_can('olama_users_ministry_configure')) wp_die('Access denied.','',array('response'=>403));
        check_admin_referer('olama_emis_save_campus');
        $input = wp_unslash($_POST);
        $page = isset($input['page']) ? sanitize_key($input['page']) : '';
        if (!isset($this->pages[$page])) wp_die('Invalid page.');
        $school = isset($input['school_id']) ? sanitize_text_field($input['school_id']) : '';
        $year = Olama_EMIS_Campus::year(isset($input['study_year']) ? $input['study_year'] : '');
        $building = isset($input['building_id']) ? absint($input['building_id']) : 0;
        $operation = isset($input['operation']) ? sanitize_key($input['operation']) : '';
        $result = new WP_Error('emis_request','طلب غير صحيح.');
        if (get_option('olama_emis_schema_version') !== OLAMA_EMIS_VERSION) wp_die('OLAMA EMIS database setup must complete before saving.');
        $rows = null;
        if ($operation === 'building' && $page === 'olama-emis-buildings') {
            $result = $this->service->save_building($input);
            if (!is_wp_error($result)) $building = $result;
        } elseif ($operation === 'floor') $result = $this->service->save_floor($input);
        elseif ($operation === 'rooms' && $page !== 'olama-emis-buildings') {
            $raw = isset($input['rooms_json']) && is_string($input['rooms_json']) ? $input['rooms_json'] : '';
            if (strlen($raw) <= 2000000) $rows = json_decode($raw,true);
            if (is_array($rows)) $result = $this->service->save_rooms($input,$rows,$page === 'olama-emis-classrooms' ? 'classroom' : 'facility');
        }
        $state = array('error'=>is_wp_error($result),'message'=>is_wp_error($result) ? $result->get_error_message() : 'حُفظت البيانات بنجاح.');
        if (is_wp_error($result)) {
            $state['input'] = $input;
            unset($state['input']['rooms_json'],$state['input']['_wpnonce']);
            $state['rows'] = $rows;
        }
        set_transient($this->token($page,$school,$year,$building),$state,20 * MINUTE_IN_SECONDS);
        wp_safe_redirect($this->url($page,$school,$year,$building,isset($input['room_page']) ? max(1,absint($input['room_page'])) : 1));
        exit;
    }

    private function hidden($context,$operation) {
        wp_nonce_field('olama_emis_save_campus');
        foreach (array_merge($context,array('action'=>'olama_emis_save_campus','operation'=>$operation)) as $key=>$value) echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
    }
    private function control($key,$spec,$value,$grid=false,$readonly=false) {
        $name = $grid ? ' data-key="' . esc_attr($key) . '"' : ' name="' . esc_attr($key) . '"';
        $type = $spec[1];
        if (is_array($type)) {
            echo '<select' . $name . ' aria-label="' . esc_attr($spec[0]) . '"><option value="">اختر</option>';
            foreach ($type as $choice) echo '<option value="' . esc_attr($choice) . '" ' . selected($value,$choice,false) . '>' . esc_html($choice) . '</option>';
            echo '</select>';
        } else {
            $html_type = in_array($type,array('integer','decimal','year'),true) ? 'number' : ($type === 'date' ? 'date' : 'text');
            $extra = $html_type === 'number' ? ' min="0" step="' . ($type === 'decimal' ? '0.0001' : '1') . '"' : '';
            if ($readonly) $extra .= ' readonly';
            if ($key === 'facility_type') $extra .= ' list="olama-emis-facility-types"';
            echo '<input' . $name . ' type="' . esc_attr($html_type) . '" value="' . esc_attr($value) . '" aria-label="' . esc_attr($spec[0]) . '" maxlength="500"' . $extra . '>';
        }
    }
    public function page() {
        if (!current_user_can('olama_users_ministry_view')) wp_die('Access denied.','',array('response'=>403));
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if (!isset($this->pages[$page])) return;
        $kind = $page === 'olama-emis-classrooms' ? 'classroom' : 'facility';
        $year = Olama_EMIS_Campus::year(isset($_GET['study_year']) ? sanitize_text_field(wp_unslash($_GET['study_year'])) : '2025-2026');
        $school = isset($_GET['school_id']) ? sanitize_text_field(wp_unslash($_GET['school_id'])) : '';
        $schools = (new Olama_EMIS_Schools())->available_schools();
        if ($school === '' && $schools) $school = (string) $schools[0]['school_id'];
        $known = in_array($school,array_map('strval',array_column($schools,'school_id')),true);
        $buildings = $known && $year ? $this->service->buildings($school,$year) : array();
        $building_id = isset($_GET['building_id']) ? absint($_GET['building_id']) : 0;
        $building = $building_id ? $this->service->building($building_id,$year) : null;
        if ($building && (string) $building['school_id'] !== $school) $building = null;
        $editable = current_user_can('olama_users_ministry_configure') && get_option('olama_emis_schema_version') === OLAMA_EMIS_VERSION;
        $state_key = $this->token($page,$school,$year,$building_id);
        $state = get_transient($state_key);
        delete_transient($state_key);
        $stage = $page === 'olama-emis-buildings' ? '٢' : ($kind === 'classroom' ? '٣' : '٤');
        echo '<div class="wrap olama-emis" dir="rtl"><header class="olama-emis__hero"><span>OLAMA EMIS · المرحلة ' . esc_html($stage) . '</span><h1>' . esc_html($this->pages[$page]) . '</h1><p>سجل الأبنية والطوابق والغرف للعام الدراسي المحدد.</p></header>';
        if (is_array($state)) echo '<div class="notice ' . ($state['error'] ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($state['message']) . '</p></div>';
        echo '<section class="olama-emis__panel"><form method="get" class="olama-emis__filters"><input type="hidden" name="page" value="' . esc_attr($page) . '"><label>المدرسة<select name="school_id">';
        foreach ($schools as $s) echo '<option value="' . esc_attr($s['school_id']) . '" ' . selected($school,$s['school_id'],false) . '>' . esc_html($s['school_name']) . '</option>';
        echo '</select></label><label>العام الدراسي<input name="study_year" value="' . esc_attr($year) . '" required pattern="[0-9]{4}-[0-9]{4}"></label><label>البناء<select name="building_id"><option value="0">' . ($page === 'olama-emis-buildings' ? 'إضافة بناء' : 'اختر البناء') . '</option>';
        foreach ($buildings as $b) echo '<option value="' . esc_attr($b['id']) . '" ' . selected($building_id,$b['id'],false) . '>بناء ' . esc_html($b['building_number'] . ($b['archived'] ? ' · مؤرشف' : (!$b['profile_id'] ? ' · لم يُسجل هذا العام' : ''))) . '</option>';
        echo '</select></label><button class="button">عرض</button></form><nav class="olama-emis__tabs">';
        foreach ($this->pages as $slug=>$title) echo '<a class="button ' . ($page === $slug ? 'button-primary' : '') . '" href="' . esc_url($this->url($slug,$school,$year,$building_id)) . '">' . esc_html($title) . '</a> ';
        echo '</nav></section>';
        if (!$known || !$year) { echo '<p>اختر مدرسة وعاماً دراسياً صحيحين.</p></div>'; return; }
        $context = array('page'=>$page,'school_id'=>$school,'study_year'=>$year,'building_id'=>$building ? $building['id'] : 0,'revision'=>$building && $building['profile_id'] ? $building['revision'] : 0,'room_page'=>isset($_GET['room_page']) ? max(1,absint($_GET['room_page'])) : 1);
        if (is_array($state) && $state['error'] && isset($state['input']['revision'])) $context['revision'] = (int) $state['input']['revision'];
        if ($page === 'olama-emis-buildings') $this->building_form($context,$building,$state,$editable);
        if ($building && $building['profile_id']) {
            $this->summary($school,$year,$building);
            $this->floor_forms($context,$state,$editable && !$building['archived']);
            if ($page !== 'olama-emis-buildings') $this->room_grid($context,$kind,$state,$editable && !$building['archived']);
        } elseif ($page !== 'olama-emis-buildings') echo '<section class="olama-emis__panel"><p>اختر بناءً واحفظ ملفه لهذا العام من صفحة بيانات البناء أولاً.</p></section>';
        echo '</div>';
    }

    private function building_form($context,$building,$state,$editable) {
        $profile = $building ? json_decode($building['profile_json'] ?: '{}',true) : array();
        if (!is_array($profile)) $profile = array();
        $failed = is_array($state) && $state['error'] && isset($state['input']['operation']) && $state['input']['operation'] === 'building';
        if ($failed) $profile = array_merge($profile,$state['input']);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><section class="olama-emis__panel"><h2>' . ($building ? 'بناء ' . esc_html($building['building_number']) : 'إضافة بناء') . '</h2>';
        $this->hidden($context,'building');
        echo '<fieldset ' . (!$editable ? 'disabled' : '') . '><div class="olama-emis__fields"><label>رقم البناء';
        $number = $building ? $building['building_number'] : (isset($profile['building_number']) ? $profile['building_number'] : '');
        $this->control('building_number',array('رقم البناء','text','K2'),$number,false,(bool) $building);
        echo '</label>';
        foreach (Olama_EMIS_Campus::building_fields() as $key=>$spec) {
            echo '<label><span>' . esc_html($spec[0]) . ($spec[2] === '' ? ' <small class="olama-emis__optional">إضافي</small>' : '') . '</span>';
            $this->control($key,$spec,isset($profile[$key]) ? $profile[$key] : '');
            echo '</label>';
        }
        echo '</div><p><label class="olama-emis__check"><input type="checkbox" name="archived" value="1" ' . checked($failed ? !empty($profile['archived']) : ($building && $building['archived']),true,false) . '>مؤرشف لهذا العام</label></p>';
        if ($editable) echo '<button class="button button-primary">حفظ ملف البناء</button>';
        echo '</fieldset></section></form>';
    }

    private function floor_forms($context,$state,$editable) {
        $floors = $this->service->floors($context['building_id'],$context['study_year']);
        echo '<section class="olama-emis__panel"><h2>الطوابق المشتركة</h2><p>الأرضي 0، الأول 1، والتسوية -1. تُستخدم مساحة الطابق نفسها في صفحتي الغرف.</p>';
        $new_floor = array('floor_number'=>'','floor_name'=>'','area'=>'');
        if (is_array($state) && $state['error'] && $state['input']['operation'] === 'floor' && !in_array((string) $state['input']['floor_number'],array_map('strval',array_column($floors,'floor_number')),true)) $new_floor = array_merge($new_floor,$state['input']);
        foreach (array_merge($floors,array($new_floor)) as $floor) {
            if (is_array($state) && $state['error'] && $state['input']['operation'] === 'floor' && (string) $floor['floor_number'] === (string) $state['input']['floor_number']) $floor = array_merge($floor,$state['input']);
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="olama-emis__floor-form">';
            $this->hidden($context,'floor');
            echo '<fieldset ' . (!$editable ? 'disabled' : '') . ' class="olama-emis__filters"><label>رقم الطابق<input name="floor_number" type="number" min="-999" max="999" value="' . esc_attr($floor['floor_number']) . '" required ' . (isset($floor['id']) ? 'readonly' : '') . '></label><label>اسم الطابق<input name="floor_name" value="' . esc_attr($floor['floor_name']) . '" maxlength="100" required></label><label>مساحة الطابق م²<input name="area" type="number" min="0" step="0.0001" value="' . esc_attr($floor['area']) . '"></label>';
            if ($editable) echo '<button class="button">' . (isset($floor['id']) ? 'حفظ الطابق' : 'إضافة طابق') . '</button>';
            echo '</fieldset></form>';
        }
        echo '</section>';
    }

    private function room_grid($context,$kind,$state,$editable) {
        $floors = $this->service->floors($context['building_id'],$context['study_year']);
        if (!$floors) { echo '<p>أضف طابقاً قبل تسجيل الغرف.</p>'; return; }
        $rooms = $this->service->rooms($context['building_id'],$context['study_year'],$kind);
        $fields = array_merge(Olama_EMIS_Campus::common_fields(),Olama_EMIS_Campus::detail_fields($kind));
        $values = array();
        foreach ($rooms as $room) {
            $common = json_decode($room['profile_json'] ?: '{}',true) ?: array();
            $detail = json_decode($room[$kind === 'classroom' ? 'classroom_json' : 'facility_json'] ?: '{}',true) ?: array();
            $values[] = array_merge($common,$detail,array('room_id'=>$room['room_id'],'room_number'=>$room['room_number'],'floor_id'=>$room['floor_id'],'use_kind'=>$room['use_kind'],'archived'=>$room['archived']));
        }
        $total = count($values);
        $last_page = max(1,(int) ceil($total / 100));
        if ($total > 0 && $total % 100 === 0) $last_page++;
        $context['room_page'] = min($last_page,$context['room_page']);
        $values = array_slice($values,($context['room_page'] - 1) * 100,100);
        if (is_array($state) && $state['error'] && $state['input']['operation'] === 'rooms' && is_array($state['rows'])) $values = $state['rows'];
        $existing_rooms = array();
        foreach ($this->service->rooms($context['building_id'],$context['study_year']) as $existing_room) $existing_rooms[] = array('room_id'=>$existing_room['room_id'],'room_number'=>$existing_room['room_number']);
        echo '<section class="olama-emis__panel"><h2>سجل الغرف</h2><p>يمكن اقتراح المساحة من الطول والعرض ثم تعديلها يدوياً. تغيير الاستخدام ينقل السجل إلى الصفحة الأخرى لهذا العام. الأرشفة تحفظ السجل التاريخي.</p><form class="olama-emis__room-form" data-existing-rooms="' . esc_attr(wp_json_encode($existing_rooms)) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        if ($last_page > 1) {
            echo '<p>صفحة ' . esc_html($context['room_page']) . ' · ' . esc_html($total) . ' غرفة مسجلة. احفظ الدفعة الحالية قبل الانتقال.</p><nav class="olama-emis__tabs">';
            for ($i=1;$i<=$last_page;$i++) echo '<a class="button" href="' . esc_url($this->url($context['page'],$context['school_id'],$context['study_year'],$context['building_id'],$i)) . '">' . esc_html($i) . '</a> ';
            echo '</nav>';
        }
        if ($kind === 'facility') {
            echo '<datalist id="olama-emis-facility-types">';
            foreach (array('مختبر علوم','مختبر حاسوب','مختبر لغات','مكتبة','غرفة معلمين','إدارة / مدير','غرفة تمريض','مقصف','مستودع','دورات مياه','صفية فارغة','غرفة مرشد') as $suggestion) echo '<option value="' . esc_attr($suggestion) . '"></option>';
            echo '</datalist>';
        }
        $this->hidden($context,'rooms');
        echo '<input type="hidden" name="rooms_json" value=""><fieldset ' . (!$editable ? 'disabled' : '') . '><noscript><p>يلزم تفعيل JavaScript لحفظ دفعة الغرف.</p></noscript><div role="alert" class="olama-emis__grid-error"></div><div class="olama-emis__scroll"><table class="widefat olama-emis__room-grid"><thead><tr><th>رقم الغرفة</th><th>الطابق</th><th>الاستخدام</th><th>مؤرشف</th>';
        foreach ($fields as $spec) echo '<th>' . esc_html($spec[0]) . ($spec[2] === '' ? '<small class="olama-emis__optional">إضافي</small>' : '') . '</th>';
        echo '<th></th></tr></thead><tbody>';
        foreach ($values as $value) $this->room_row($value,$fields,$floors,$kind);
        echo '</tbody></table></div><template class="olama-emis__row-template">';
        $this->room_row(array(),$fields,$floors,$kind);
        echo '</template>';
        if ($editable) echo '<p><button type="button" class="button olama-emis__add-room">إضافة غرفة</button> <button class="button button-primary">حفظ دفعة الغرف</button></p>';
        echo '</fieldset></form></section>';
    }

    private function room_row($value,$fields,$floors,$kind) {
        $id = isset($value['room_id']) ? (int) $value['room_id'] : 0;
        echo '<tr data-room-id="' . esc_attr($id) . '"><td><input data-key="room_number" value="' . esc_attr(isset($value['room_number']) ? $value['room_number'] : '') . '" maxlength="50" aria-label="رقم الغرفة" ' . ($id ? 'readonly' : '') . '></td><td><select data-key="floor_id" aria-label="الطابق"><option value="">اختر</option>';
        foreach ($floors as $floor) echo '<option value="' . esc_attr($floor['id']) . '" ' . selected(isset($value['floor_id']) ? $value['floor_id'] : '',$floor['id'],false) . '>' . esc_html($floor['floor_number'] . ' · ' . $floor['floor_name']) . '</option>';
        echo '</select></td><td><select data-key="use_kind" aria-label="الاستخدام">';
        foreach (array('classroom'=>'صفية','facility'=>'غير صفية') as $key=>$label) echo '<option value="' . esc_attr($key) . '" ' . selected(isset($value['use_kind']) ? $value['use_kind'] : $kind,$key,false) . '>' . esc_html($label) . '</option>';
        echo '</select></td><td><input type="checkbox" data-key="archived" aria-label="مؤرشف" ' . checked(!empty($value['archived']),true,false) . '></td>';
        foreach ($fields as $key=>$spec) { echo '<td>'; $this->control($key,$spec,isset($value[$key]) ? $value[$key] : '',true); if ($key === 'area') echo '<button type="button" class="button-link olama-emis__calculate-area">اقتراح المساحة</button>'; echo '</td>'; }
        echo '<td>' . (!$id ? '<button type="button" class="button-link-delete olama-emis__remove-room">إزالة الصف الجديد</button>' : '') . '</td></tr>';
    }

    private function summary($school,$year,$building) {
        $summary = $this->service->school_summary($school,$year);
        $labels = array('classrooms'=>'غرف صفية','facilities'=>'غرف غير صفية','science_labs'=>'مختبرات علوم','computer_labs'=>'مختبرات حاسوب','language_labs'=>'مختبرات لغات','library_count'=>'مكتبات','teacher_capacity'=>'سعة غرف المعلمين','sanitary_units'=>'وحدات المراحيض');
        echo '<section class="olama-emis__panel"><h2>ملخص المدرسة · ' . esc_html($year) . '</h2><div class="olama-emis__cards olama-emis__campus-cards">';
        foreach ($labels as $key=>$label) echo '<div><strong>' . esc_html($summary['totals'][$key] === null ? 'غير مكتمل' : $summary['totals'][$key]) . '</strong><span>' . esc_html($label) . '</span></div>';
        echo '</div><p>يحسب الملخص الغرف النشطة في الأبنية النشطة المسجلة لهذا العام. الغرف غير المصنفة لا تدخل في أعداد المختبرات والمكتبات.</p>';
        if ($summary['totals']['uncategorized_facilities']) echo '<p class="olama-emis__mismatch">غرف تحتاج تصنيفاً لاستكمال الملخص: ' . esc_html($summary['totals']['uncategorized_facilities']) . '</p>';
        $school_row = (new Olama_EMIS_Schools())->find($school,$year);
        $profile = $school_row ? json_decode($school_row['profile_json'],true) : array();
        foreach (array('library_count','science_labs','computer_labs','language_labs') as $key) if (isset($profile[$key]) && $profile[$key] !== '' && (int) $profile[$key] !== $summary['totals'][$key]) echo '<p class="olama-emis__mismatch">' . esc_html($labels[$key]) . ': ملف المدرسة ' . esc_html($profile[$key]) . ' · سجل الغرف ' . esc_html($summary['totals'][$key]) . '</p>';
        if ($summary['floor_mismatches']) echo '<p class="olama-emis__mismatch">عدد الطوابق المدخل يختلف عن سجل الطوابق في الأبنية: ' . esc_html(implode('، ',$summary['floor_mismatches'])) . '</p>';
        echo '</section>';
    }
}
