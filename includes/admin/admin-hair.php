<?php
if (!defined('ABSPATH')) exit;

/** =========================
 *  Helpers for sanitization
 * ========================= */
function shec_sanitize_api_key( $val ) {
    $val = is_string($val) ? wp_unslash($val) : $val;
    // فقط حروف/اعداد/._:- و خط‌تیره
    return preg_replace('/[^A-Za-z0-9_\-\.\:]/', '', $val);
}
function shec_sanitize_prompt_text( $val ) {
    $val = is_string($val) ? wp_unslash($val) : $val;  // 🔥 مهم
    // هیچ HTML لازم نداریم؛ فقط متن خام
    $val = wp_kses( $val, [] );
    return rtrim($val);
}

/** =========================
 *  منوهای ادمین
 * ========================= */
function shec_add_admin_menu() {
    add_menu_page(
        'هوش مصنوعی',
        'هوش مصنوعی',
        'manage_options',
        'shec-form',
        'shec_display_data',
        'dashicons-chart-pie',
        6
    );

    add_submenu_page(
        'shec-form',
        'تنظیمات',
        'تنظیمات',
        'manage_options',
        'shec-settings',
        'shec_display_settings'
    );
}
add_action('admin_menu', 'shec_add_admin_menu');

/** =========================
 *  لیست رکوردها
 * ========================= */
function shec_display_data() {
    global $wpdb;

    $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shec_users");

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">داده‌های فرم هوشمند فخرایی</h1>';

    if (!empty($results)) {
        echo '<table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">شناسه</th>
                        <th scope="col" class="manage-column">جنسیت</th>
                        <th scope="col" class="manage-column">سن</th>
                        <th scope="col" class="manage-column">اعتماد به نفس</th>
                        <th scope="col" class="manage-column">نام</th>
                        <th scope="col" class="manage-column">نام خانوادگی</th>
                        <th scope="col" class="manage-column">مشاهده</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($results as $row) {
            $data = json_decode($row->data, true) ?: [];

            $gender     = $data['gender'] ?? 'N/A';
            $age        = $data['age'] ?? 'N/A';
            $confidence = $data['confidence'] ?? 'N/A';
            $first_name = $data['contact']['first_name'] ?? 'N/A';
            $last_name  = $data['contact']['last_name'] ?? 'N/A';

            $url = add_query_arg(
                ['page'=>'shec-form-data', 'user_id'=>$row->id],
                admin_url('admin.php')
            );

            echo '<tr>';
            echo '<td>' . esc_html($row->id) . '</td>';
            echo '<td>' . esc_html($gender) . '</td>';
            echo '<td>' . esc_html($age) . '</td>';
            echo '<td>' . esc_html($confidence) . '</td>';
            echo '<td>' . esc_html($first_name) . '</td>';
            echo '<td>' . esc_html($last_name) . '</td>';
            echo '<td><a class="button" target="_blank" href="' . esc_url($url) . '">مشاهده</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>داده‌ای یافت نشد.</p>';
    }
    echo '</div>';
}

/** =========================
 *  جزئیات یک رکورد
 * ========================= */
function shec_display_user_details() {
    global $wpdb;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (!$user_id) {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
        return;
    }

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}shec_users WHERE id = %d", $user_id
    ) );

    if ($row) {
        $data = json_decode($row->data, true) ?: [];

        echo '<div class="wrap">';
        echo '<h1>جزئیات داده‌های کاربر</h1>';
        echo '<p><strong>شناسه رکورد:</strong> ' . esc_html($row->id) . '</p>';
        echo '<p><strong>جنسیت:</strong> ' . esc_html($data['gender'] ?? 'N/A') . '</p>';
        echo '<p><strong>سن:</strong> ' . esc_html($data['age'] ?? 'N/A') . '</p>';
        echo '<p><strong>اعتماد به نفس:</strong> ' . esc_html($data['confidence'] ?? 'N/A') . '</p>';
        echo '<p><strong>الگوی ریزش مو:</strong> ' . esc_html($data['loss_pattern'] ?? 'N/A') . '</p>';
        echo '<p><strong>نام:</strong> ' . esc_html($data['contact']['first_name'] ?? 'N/A') . '</p>';
        echo '<p><strong>نام خانوادگی:</strong> ' . esc_html($data['contact']['last_name'] ?? 'N/A') . '</p>';
        echo '<p><strong>شماره تلفن:</strong> ' . esc_html($data['mobile'] ?? ($data['contact']['mobile'] ?? 'N/A')) . '</p>';
        echo '<p><strong>سوشال مدیا:</strong> ' . esc_html($data['contact']['social'] ?? 'N/A') . '</p>';

        echo '<h2>سوالات و پاسخ‌ها:</h2>';
        $qs  = $data['ai']['followups']['questions'] ?? [];
        $ans = $data['ai']['followups']['answers'] ?? [];
        if ($qs) {
            echo '<ol>';
            foreach ($qs as $i => $q) {
                $a = $ans[$i] ?? '';
                echo '<li><strong>'.esc_html($q).'</strong><br><em>پاسخ کاربر:</em> '.esc_html($a ?: '—').'</li>';
            }
            echo '</ol>';
        } else {
            echo '<p>—</p>';
        }

        echo '<h2>نتیجه‌گیری هوش مصنوعی</h2>';
        $final = $data['ai']['final'] ?? null;
        if ($final) {
            echo '<p><strong>روش:</strong> '.esc_html($final['method'] ?? '—').'</p>';
            echo '<p><strong>تخمین گرافت:</strong> '.esc_html($final['graft_count'] ?? '—').'</p>';
            echo '<p><strong>تحلیل:</strong> '.esc_html($final['analysis'] ?? '—').'</p>';
        } else {
            echo '<p>—</p>';
        }
        echo '</div>';
    } else {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
    }
}
add_action('admin_menu', function () {
    add_submenu_page('shec-form', 'جزئیات داده‌ها', 'جزئیات داده‌ها', 'manage_options', 'shec-form-data', 'shec_display_user_details');
});

/** =========================
 *  صفحه تنظیمات (ذخیره + فرم)
 * ========================= */
function shec_display_settings() {
    if (!current_user_can('manage_options')) return;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('shec_settings_save','shec_settings_nonce')) {

        if (isset($_POST['shec_api_key'])) {
            update_option('shec_api_key', shec_sanitize_api_key($_POST['shec_api_key']));
        }
        if (isset($_POST['shec_sms_api'])) {
            update_option('shec_sms_api', sanitize_text_field( wp_unslash($_POST['shec_sms_api']) ));
        }
        if (isset($_POST['shec_telegram_api'])) {
            update_option('shec_telegram_api', sanitize_text_field( wp_unslash($_POST['shec_telegram_api']) ));
        }
        // ✅ پرامپت‌های داینامیک (unslash + no HTML)
        if (isset($_POST['shec_prompt_questions'])) {
            update_option('shec_prompt_questions', shec_sanitize_prompt_text($_POST['shec_prompt_questions']));
        }
        if (isset($_POST['shec_prompt_final'])) {
            update_option('shec_prompt_final', shec_sanitize_prompt_text($_POST['shec_prompt_final']));
        }

        echo '<div class="updated"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
    }

    $api_key  = get_option('shec_api_key', '');
    $sms_api  = get_option('shec_sms_api', '');
    $telegram = get_option('shec_telegram_api', '');

    $p_q = get_option('shec_prompt_questions', '');
    $p_f = get_option('shec_prompt_final', '');

    // اگر خالی‌اند، فقط برای نمایش دیفالت نشان بده
    if (!$p_q && function_exists('shec_prompt_questions_default')) $p_q = shec_prompt_questions_default();
    if (!$p_f && function_exists('shec_prompt_final_default'))     $p_f = shec_prompt_final_default();

    echo '<div class="wrap">';
    echo '<h1>تنظیمات افزونه</h1>';
    echo '<form method="POST">';
    wp_nonce_field('shec_settings_save','shec_settings_nonce');

    echo '<h2>تنظیمات API OpenAI</h2>';
    echo '<input type="text" name="shec_api_key" value="' . esc_attr($api_key) . '" style="width: 420px;" />';

    echo '<h2 style="margin-top:40px;">پنل SMS</h2>';
    echo '<input type="text" name="shec_sms_api" value="' . esc_attr($sms_api) . '" style="width: 420px;" />';

    echo '<h2 style="margin-top:40px;">ربات تلگرام</h2>';
    echo '<input type="text" name="shec_telegram_api" value="' . esc_attr($telegram) . '" style="width: 420px;" />';

    // ✅ دو textarea برای پرامپت‌ها
    echo '<h2 style="margin-top:40px;">پرامپت سؤالات (پس از استپ ۴)</h2>';
    echo '<p style="color:#555">از {{SUMMARY_JSON}} داخل متن استفاده کن. خروجی باید <strong>فقط JSON</strong> با کلید <code>questions</code> و ۴ سؤال باشد.</p>';
    echo '<textarea name="shec_prompt_questions" rows="14" style="width:100%;max-width:860px;">' . esc_textarea($p_q) . '</textarea>';

    echo '<h2 style="margin-top:40px;">پرامپت نهایی (استپ ۵)</h2>';
    echo '<p style="color:#555">از {{PACK_JSON}} داخل متن استفاده کن. خروجی باید <strong>فقط JSON</strong> با کلیدهای <code>method</code>، <code>graft_count</code>، <code>analysis</code> باشد.</p>';
    echo '<textarea name="shec_prompt_final" rows="14" style="width:100%;max-width:860px;">' . esc_textarea($p_f) . '</textarea>';

    echo '<p><input type="submit" value="ذخیره" class="button-primary" /></p>';
    echo '</form>';
    echo '</div>';
}

/** =========================
 *  پچ یک‌باره برای پرامپت‌های اسلش‌دار
 *  /wp-admin/?shec_fix_prompts=1
 * ========================= */
add_action('admin_init', function(){
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['shec_fix_prompts'])) return;

    foreach (['shec_prompt_questions','shec_prompt_final'] as $k) {
        $v = get_option($k, '');
        if ($v !== '') update_option($k, wp_unslash($v));
    }
    wp_die('Prompts unslashed. حالا می‌توانید این بلاک را از فایل حذف کنید.');
});
