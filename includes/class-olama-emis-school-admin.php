<?php
if (!defined('ABSPATH')) exit;

final class Olama_EMIS_School_Admin {
    private $slug = 'olama-emis-schools';
    private $service;

    public function __construct() {
        $this->service = new Olama_EMIS_Schools();
        add_action('admin_menu', array($this, 'menu'), 31);
        add_action('admin_post_olama_emis_save_school', array($this, 'save'));
    }

    public function menu() {
        add_submenu_page('olama-emis', 'البيانات الأساسية للمدرسة', 'البيانات الأساسية للمدرسة', 'olama_users_ministry_view', $this->slug, array($this, 'page'));
    }

    private function url($school_id = '', $year = '2025-2026', $message = '') {
        $args = array('page' => $this->slug, 'school_id' => $school_id, 'study_year' => $year);
        if ($message !== '') $args['emis_notice'] = $message;
        return add_query_arg($args, admin_url('admin.php'));
    }

    public function save() {
        if (!current_user_can('olama_users_ministry_configure')) wp_die('Access denied.', '', array('response' => 403));
        check_admin_referer('olama_emis_save_school');
        $input = wp_unslash($_POST);
        $school_id = isset($input['school_id']) ? sanitize_text_field((string) $input['school_id']) : '';
        $year = isset($input['study_year']) ? sanitize_text_field((string) $input['study_year']) : '2025-2026';
        $result = $this->service->save($input);
        $message = is_wp_error($result) ? $result->get_error_message() : 'حُفظ ملف المدرسة بنجاح.';
        wp_safe_redirect($this->url($school_id, $year, $message));
        exit;
    }

    public function page() {
        if (!current_user_can('olama_users_ministry_view')) wp_die('Access denied.', '', array('response' => 403));
        $schools = $this->service->available_schools();
        $school_id = isset($_GET['school_id']) ? sanitize_text_field(wp_unslash($_GET['school_id'])) : '';
        $year = isset($_GET['study_year']) ? str_replace('/', '-', sanitize_text_field(wp_unslash($_GET['study_year']))) : '2025-2026';
        if ($school_id === '' && $schools) $school_id = (string) $schools[0]['school_id'];
        $known = false;
        $core_name = '';
        foreach ($schools as $school) if ((string) $school['school_id'] === $school_id) { $known = true; $core_name = $school['school_name']; }
        $row = $known && preg_match('/^\d{4}-\d{4}$/', $year) ? $this->service->find($school_id, $year) : null;
        $profile = $row ? json_decode($row['profile_json'], true) : array();
        if (!is_array($profile)) $profile = array();
        if (!$row && $known) {
            $profile['school_name'] = $core_name;
        }
        echo '<div class="wrap olama-emis" dir="rtl"><header class="olama-emis__hero"><span>OLAMA EMIS · المرحلة ١</span><h1>البيانات الأساسية للمدرسة</h1><p>ملف مستقل لكل مدرسة وعام دراسي. تُحفظ التعديلات كمسودة حتى اعتماد النموذج الوزاري الأخير.</p></header>';
        if (isset($_GET['emis_notice'])) echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['emis_notice']))) . '</p></div>';
        echo '<section class="olama-emis__panel"><h2>تحديد الملف</h2><form method="get" class="olama-emis__filters"><input type="hidden" name="page" value="' . esc_attr($this->slug) . '"><label>المدرسة<select name="school_id" required><option value="">اختر المدرسة</option>';
        foreach ($schools as $school) echo '<option value="' . esc_attr($school['school_id']) . '" ' . selected($school_id, $school['school_id'], false) . '>' . esc_html($school['school_name'] . ' (' . $school['school_id'] . ')') . '</option>';
        echo '</select></label><label>العام الدراسي<input name="study_year" value="' . esc_attr($year) . '" pattern="[0-9]{4}-[0-9]{4}" required></label><button class="button">عرض الملف</button></form></section>';
        if (!$known) { echo '<section class="olama-emis__panel"><p>لا توجد مدرسة محددة في قيد OLAMA Core.</p></section></div>'; return; }
        echo '<div class="olama-emis__cards"><div><strong>' . esc_html($row ? 'مسودة' : 'جديد') . '</strong><span>حالة الملف</span></div><div><strong>' . esc_html($year) . '</strong><span>العام الدراسي</span></div><div><strong>' . esc_html($school_id) . '</strong><span>معرف OLAMA Core</span></div></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('olama_emis_save_school');
        echo '<input type="hidden" name="action" value="olama_emis_save_school"><input type="hidden" name="school_id" value="' . esc_attr($school_id) . '"><input type="hidden" name="study_year" value="' . esc_attr($year) . '"><input type="hidden" name="revision" value="' . esc_attr($row ? $row['revision'] : 0) . '">';
        $titles = array('identity' => 'هوية المدرسة', 'location' => 'الموقع والإدارة', 'operations' => 'التشغيل والمراحل', 'contact' => 'التواصل والإدارة', 'campus' => 'الأرض والمرافق المدرسية');
        foreach (Olama_EMIS_Schools::fields() as $group => $fields) {
            echo '<section class="olama-emis__panel"><h2>' . esc_html($titles[$group]) . '</h2><div class="olama-emis__fields">';
            foreach ($fields as $key => $spec) {
                $value = isset($profile[$key]) ? $profile[$key] : '';
                echo '<label><span>' . esc_html($spec[0]) . '</span>';
                if ($spec[2] === 'governorate' || $spec[2] === 'school_gender') {
                    $choices = $spec[2] === 'governorate' ? Olama_EMIS_Schools::governorates() : array('ذكور', 'إناث', 'مختلط');
                    echo '<select name="' . esc_attr($key) . '"><option value="">اختر</option>';
                    foreach ($choices as $choice) echo '<option value="' . esc_attr($choice) . '" ' . selected($value, $choice, false) . '>' . esc_html($choice) . '</option>';
                    echo '</select>';
                } else {
                    $type = in_array($spec[2], array('email', 'url', 'tel', 'number'), true) ? $spec[2] : 'text';
                    $hint = $spec[1] === '' ? ' title="حقل داخلي إضافي؛ غير موجود في نموذج 2025/2026"' : '';
                    $step = $type === 'number' ? ' step="any" min="0"' : '';
                    echo '<input name="' . esc_attr($key) . '" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '" maxlength="500"' . $step . $hint . '>';
                }
                echo '</label>';
            }
            echo '</div></section>';
        }
        if (current_user_can('olama_users_ministry_configure')) echo '<p class="olama-emis__save"><button class="button button-primary button-large">حفظ مسودة المدرسة</button></p>';
        echo '</form></div>';
    }
}
