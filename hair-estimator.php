<?php
/**
 * Plugin Name: Smart Hair Graft Calculator
 * Plugin URI:  https://github.com/salarshirkhani/Smart-Hair-Graft-Calculator
 * Description: ابزار هوشمند محاسبه تعداد تار مو با AJAX و OpenAI، حالا به عنوان یک شورت‌کد وردپرس.
 * Version:     1.0.0
 * Author:      تیم شما
 * Text Domain: smart-hair-calculator
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) تعریف ثابت‌ها
define( 'SHEC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHEC_URL', plugin_dir_url( __FILE__ ) );

// 2) ساخت جدول دیتابیس و ایجاد برگه
register_activation_hook( __FILE__, 'shec_activate_plugin' );
function shec_activate_plugin() {
    global $wpdb;
    $table = $wpdb->prefix . 'shec_users';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_user_id BIGINT UNSIGNED DEFAULT NULL,
        data LONGTEXT NOT NULL,
        created DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // ایجاد برگه فرم
    $page = get_page_by_path( 'hair-graft-calculator' );
    if ( ! $page ) {
        wp_insert_post( [
            'post_title'   => 'Hair Graft Calculator',
            'post_name'    => 'hair-graft-calculator',
            'post_content' => '[smart_hair_calculator]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
    }
}

// 3) لود فایل‌های استایل و اسکریپت با nonce برای امنیت
add_action( 'wp_enqueue_scripts', 'shec_enqueue_assets' );
function shec_enqueue_assets() {
    wp_enqueue_script('jquery');

    // toastr
    wp_enqueue_style( 'toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css' );
    wp_enqueue_script( 'toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', [], null, true );

    // jsPDF برای دانلود PDF
    wp_enqueue_script(
        'jspdf',
        'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
        [],
        '2.5.1',
        true
    );

    // استایل و اسکریپت خود افزونه
    wp_enqueue_style( 'shec-style', SHEC_URL . 'public/assets/scss/style.css' );
    wp_enqueue_script( 'shec-form-js', SHEC_URL . 'public/assets/js/form.js', ['jquery','toastr-js','jspdf'], '1.0.1', true );

    // فقط یک‌بار localize
    wp_localize_script('shec-form-js', 'shec_ajax', [
        'url'      => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('shec_nonce'),
        'img_path' => plugins_url('public/assets/img/', __FILE__),
    ]);
}

if ( ! function_exists('shec_is_localhost') ) {
    function shec_is_localhost() {
        $h = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        return in_array($h, ['localhost', '127.0.0.1'], true);
    }
}

// در لوکال بای‌پس؛ در سرور اصلی حتماً نانس چک می‌شود
if ( ! function_exists('shec_check_nonce_or_bypass') ) {
    function shec_check_nonce_or_bypass() {
        if ( shec_is_localhost() || (defined('WP_DEBUG') && WP_DEBUG) ) {
            return true; // بای‌پس در محیط توسعه
        }
        check_ajax_referer('shec_nonce','_nonce');
        return true;
    }
}

// 5) شورت‌کد برای نمایش فرم
add_shortcode( 'smart_hair_calculator', function() {
    ob_start();
    include SHEC_PATH . 'templates/form-template.php';
    return ob_get_clean();
});

// ================= STEP 1 =================
if ( ! function_exists('shec_normalize_mobile') ) {
    function shec_normalize_mobile($mobile_raw) {
        // فقط عدد
        $m = preg_replace('/\D+/', '', (string)$mobile_raw);

        // 0098 / +98 / 98 => حذف پیش‌شماره ایران
        if (strpos($m, '0098') === 0) $m = substr($m, 4);
        if (strpos($m, '98') === 0)   $m = substr($m, 2);

        // اگر با 9 شروع شد، 0 اضافه کن
        if (strpos($m, '9') === 0) $m = '0' . $m;

        // الان باید 09xxxxxxxxx شود (11 رقم)
        if (preg_match('/^09\d{9}$/', $m)) {
            return $m;
        }

        // اگر نامعتبر بود، همون ورودی خام رو برگردون
        return $mobile_raw;
    }
}


add_action( 'wp_ajax_shec_step1', 'shec_handle_step1' );
add_action( 'wp_ajax_nopriv_shec_step1', 'shec_handle_step1' );
function shec_handle_step1() {
    shec_check_nonce_or_bypass(); // ← اینجا
    global $wpdb;

    // نرمال‌سازی ساده
    $normalize = function($m){
        $m = preg_replace('/\D+/', '', (string)$m);
        if (strpos($m,'0098')===0) $m = substr($m,4);
        if (strpos($m,'98')===0)   $m = substr($m,2);
        if (strpos($m,'9')===0)    $m = '0'.$m;
        return $m;
    };

    $gender     = sanitize_text_field($_POST['gender'] ?? '');
    $age        = sanitize_text_field($_POST['age'] ?? '');
    $confidence = sanitize_text_field($_POST['confidence'] ?? '');
    $mobile     = $normalize(sanitize_text_field($_POST['mobile'] ?? ''));

    $valid_ages = ['18-23','24-29','30-35','36-43','44-56','+56'];
    if (!$gender || !in_array($age,$valid_ages,true)) {
        wp_send_json_error(['message' => 'لطفاً جنسیت و بازه سنی معتبر وارد کنید.']);
    }
    if (!preg_match('/^09\d{9}$/',$mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست. مثال: 09xxxxxxxxx']);
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        $maxId = (int) $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}shec_users");
        $user_id = $maxId > 0 ? ($maxId + 1) : 1;
    }

    $data = [
        'gender'     => $gender,
        'age'        => $age,
        'mobile'     => $mobile,        // ✅ موبایل در ریشه
        'confidence' => $confidence
    ];

    $wpdb->insert( $wpdb->prefix . 'shec_users', [
        'wp_user_id' => $user_id,
        'data'       => wp_json_encode($data)
    ] );

    wp_send_json_success(['user_id' => $user_id]);
}


// ================= STEP 2 =================
add_action( 'wp_ajax_shec_step2', 'shec_handle_step2' );
add_action( 'wp_ajax_nopriv_shec_step2', 'shec_handle_step2' );
function shec_handle_step2() {
shec_check_nonce_or_bypass();

    global $wpdb;

    $user_id = intval($_POST['user_id'] ?? 0);
    $pattern = sanitize_text_field($_POST['loss_pattern'] ?? '');

    if ($user_id <= 0 || empty($pattern)) {
        wp_send_json_error(['message' => 'اطلاعات مرحله ۲ ناقص است']);
    }

    $existing_json = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id = %d", $user_id
    ));
    $data = $existing_json ? json_decode($existing_json, true) : [];
    $data['loss_pattern'] = $pattern;

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($data)],
        ['wp_user_id' => $user_id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success();
}


// ================= STEP 3 =================
add_action( 'wp_ajax_shec_step3', 'shec_handle_step3' );
add_action( 'wp_ajax_nopriv_shec_step3', 'shec_handle_step3' );
function shec_handle_step3(){
shec_check_nonce_or_bypass();

    global $wpdb;

    $user_id = intval($_POST['user_id'] ?? 0);
    $position = sanitize_text_field($_POST['position'] ?? '');

    if (!$user_id || empty($_FILES)) {
        wp_send_json_error(['message' => 'فایل یا کاربر معتبر نیست.']);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload($_FILES[array_key_first($_FILES)], ['test_form' => false]);

    if ( isset($uploaded['error']) ) {
        wp_send_json_error(['message' => $uploaded['error']]);
    }

    $existing_json = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id = %d", $user_id
    ));
    $data = $existing_json ? json_decode($existing_json, true) : [];
    if (!isset($data['uploads'])) $data['uploads'] = [];
    $data['uploads'][$position] = $uploaded['url'];

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($data)],
        ['wp_user_id' => $user_id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success(['file' => $uploaded['url']]);
}


// ================= STEP 4 =================
add_action( 'wp_ajax_shec_step4', 'shec_handle_step4' );
add_action( 'wp_ajax_nopriv_shec_step4', 'shec_handle_step4' );
function shec_handle_step4(){
    shec_check_nonce_or_bypass(); // یا همان check_ajax_referer که قبلاً داشتی
    global $wpdb;

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) wp_send_json_error(['message' => 'کاربر معتبر نیست.']);

    // ✅ اجبار انتخاب رادیوها
    $has_medical = isset($_POST['has_medical']) ? sanitize_text_field($_POST['has_medical']) : '';
    $has_meds    = isset($_POST['has_meds']) ? sanitize_text_field($_POST['has_meds']) : '';

    if (!in_array($has_medical, ['yes','no'], true)) {
        wp_send_json_error(['message' => 'لطفاً وضعیت ابتلا به بیماری را مشخص کنید.']);
    }
    if (!in_array($has_meds, ['yes','no'], true)) {
        wp_send_json_error(['message' => 'لطفاً وضعیت مصرف دارو را مشخص کنید.']);
    }
    if ($has_meds === 'yes') {
        $meds_list = trim(sanitize_text_field($_POST['meds_list'] ?? ''));
        if ($meds_list === '') {
            wp_send_json_error(['message' => 'نام دارو را وارد کنید.']);
        }
    }

    // بقیه‌ی فیلدها
    $medical_data = array_map('sanitize_text_field', $_POST);
    unset($medical_data['_nonce'], $medical_data['action'], $medical_data['user_id']);

    $existing_json = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id = %d", $user_id
    ));
    $data = $existing_json ? json_decode($existing_json, true) : [];
    $data['medical'] = $medical_data;

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($data)],
        ['wp_user_id' => $user_id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success();
}

// ================= STEP 5 =================
add_action( 'wp_ajax_shec_step5', 'shec_handle_step5' );
add_action( 'wp_ajax_nopriv_shec_step5', 'shec_handle_step5' );
function shec_handle_step5(){
    shec_check_nonce_or_bypass(); // ← اینجا
    global $wpdb;

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) wp_send_json_error(['message' => 'کاربر معتبر نیست.']);

    $existing_json = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id = %d", $user_id
    ));
    if ( is_null($existing_json) ) {
        wp_send_json_error('داده‌ای برای این کاربر پیدا نشد');
    }

    $data = json_decode($existing_json, true);

    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
    $state      = sanitize_text_field($_POST['state'] ?? '');
    $city       = sanitize_text_field($_POST['city'] ?? '');
    $social     = sanitize_text_field($_POST['social'] ?? '');

    if (!$first_name || !$last_name || !$state || !$city || !$social) {
        wp_send_json_error(['message' => 'تمامی فیلدهای مرحله ۵ (به جز موبایل) باید پر شوند.']);
    }

    if (!isset($data['contact'])) $data['contact'] = [];
    $data['contact'] = array_merge($data['contact'], [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'state'      => $state,
        'city'       => $city,
        'social'     => $social
        // mobile قبلی حفظ می‌شود
    ]);

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($data)],
        ['wp_user_id' => $user_id],
        ['%s'],
        ['%d']
    );

    $ai_mock = [
        'method' => 'FIT',
        'graft_count' => 2800,
        'analysis' => 'با توجه به الگوی ریزش و سن، روش FIT مناسب‌تر است.'
    ];

    wp_send_json_success([
        'user'      => $data,
        'ai_result' => wp_json_encode($ai_mock)
    ]);
}



function shec_remove_theme_styles_and_scripts() {
    if (is_page() && has_shortcode(get_post()->post_content, 'smart_hair_calculator')) {
        // حذف استایل‌ها
        wp_dequeue_style('wp-block-library'); // حذف استایل بلوک‌های وردپرس
        wp_dequeue_style('wp-block-navigation'); // حذف استایل ناوبری
        wp_dequeue_style('wp-block-post-title'); // حذف استایل عنوان پست
        wp_dequeue_style('wp-block-group'); // حذف استایل گروه بلوک‌ها
    }
}
add_action('wp_enqueue_scripts', 'shec_remove_theme_styles_and_scripts', 100) ;

function hide_header_footer_on_shec_page() {
    if (is_page() && has_shortcode(get_post()->post_content, 'smart_hair_calculator')) {
        echo '<style>
            body {
                margin: 0;
                padding: 0;
            }
            main{
                margin-top:0px !important ;
                padding-left:0px !important;
                padding-right:0px !important;
                padding-top:0px !important ;
            }
            h1, h2, h3, h4, h5, h6 {
                display: none;  
            }
            .site-header, .site-footer, .wp-admin-bar, .header, .footer {
                display: none;
            }
            header , footer{
                display: none !important;
            }   
            #content {
                margin-top: 0 !important;
                padding-top: 0 !important;
                height: 100vh; 
            }
            #progress-wrapper{
                 width: 100vw !important;
                 max-width:100% !important;
            }
            :root :where(.is-layout-constrained) > * {
                margin-block-start: -0.8rem;
            }
            .wp-block-group{
                padding-top:-10px !important;
            }
                :root :where(.is-layout-constrained) > :last-child {
                margin-block-end: 0;
                padding-top: 0px !important;
            }
        </style>';
    }
}
add_action('wp_head', 'hide_header_footer_on_shec_page');

function remove_title_for_shec_page($title) {
    // بررسی اینکه آیا صفحه دارای شورت‌کد است
    if (is_page() && has_shortcode(get_post()->post_content, 'smart_hair_calculator')) {
        return '';  // حذف عنوان صفحه
    }
    return $title;
}

// حذف عنوان صفحه برای شورت‌کد افزونه
add_filter('wp_title', 'remove_title_for_shec_page', 10, 2);
add_filter('document_title', 'remove_title_for_shec_page', 10, 2);



function custom_page_styles_for_fullscreen() {
    if (is_page() && has_shortcode(get_post()->post_content, 'smart_hair_calculator')) {
        echo '<style>
            #content {
                display: block;
                height: 100%;
                width: 100%;
            }

        </style>';
    }
}
add_action('wp_head', 'custom_page_styles_for_fullscreen');

// تغییر منوی ادمین
function shec_add_admin_menu() {
    add_menu_page(
        'هوش مصنوعی', // عنوان صفحه
        'هوش مصنوعی', // عنوان منو در داشبورد
        'manage_options', // مجوز دسترسی
        'shec-form', // شناسه منو
        'shec_display_data', // تابعی که داده‌ها را نمایش می‌دهد
        'dashicons-chart-pie', // آیکون منو
        6 // موقعیت منو
    );

    add_submenu_page(
        'shec-form',
        'تنظیمات', // عنوان صفحه زیرمنو
        'تنظیمات', // عنوان منو
        'manage_options', // مجوز دسترسی
        'shec-settings', // شناسه صفحه زیرمنو
        'shec_display_settings' // تابع برای نمایش تنظیمات
    );
}
add_action('admin_menu', 'shec_add_admin_menu');

// نمایش داده‌ها در صفحه ادمین
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
            $data = json_decode($row->data, true);

            // استخراج داده‌ها
            $gender = $data['gender'] ?? 'N/A';
            $age = $data['age'] ?? 'N/A';
            $confidence = $data['confidence'] ?? 'N/A';
            $first_name = $data['contact']['first_name'] ?? 'N/A';
            $last_name = $data['contact']['last_name'] ?? 'N/A';

            echo '<tr>';
            echo '<td>' . $row->id . '</td>';
            echo '<td>' . $gender . '</td>';
            echo '<td>' . $age . '</td>';
            echo '<td>' . $confidence . '</td>';
            echo '<td>' . $first_name . '</td>';
            echo '<td>' . $last_name . '</td>';
            echo '<td><a href="' . admin_url('admin.php?page=shec-form-data&user_id=' . $row->id) . '" target="_blank" class="button">مشاهده</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>داده‌ای یافت نشد.</p>';
    }
    echo '</div>';
}

// نمایش جزئیات داده‌های هر کاربر در تب جدید
// نمایش جزئیات داده‌های کاربر در تب جدید
function shec_display_user_details() {
    global $wpdb;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (!$user_id) {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
        return;
    }

    // گرفتن داده‌ها از دیتابیس
    $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shec_users WHERE id = $user_id");

    if ($row) {
        $data = json_decode($row->data, true);

        echo '<div class="wrap">';
        echo '<h1>جزئیات داده‌های کاربر</h1>';
        echo '<p><strong>شناسه کاربر:</strong> ' . $row->id . '</p>';
        echo '<p><strong>جنسیت:</strong> ' . ($data['gender'] ?? 'N/A') . '</p>';
        echo '<p><strong>سن:</strong> ' . ($data['age'] ?? 'N/A') . '</p>';
        echo '<p><strong>اعتماد به نفس:</strong> ' . ($data['confidence'] ?? 'N/A') . '</p>';
        echo '<p><strong>الگوی ریزش مو:</strong> ' . ($data['loss_pattern'] ?? 'N/A') . '</p>';
        echo '<p><strong>نام:</strong> ' . ($data['contact']['first_name'] ?? 'N/A') . '</p>';
        echo '<p><strong>نام خانوادگی:</strong> ' . ($data['contact']['last_name'] ?? 'N/A') . '</p>';
        echo '<p><strong>شماره تلفن:</strong> ' . esc_html($data['mobile'] ?? ($data['contact']['mobile'] ?? 'N/A')) . '</p>';
        echo '<p><strong>سوشال مدیا:</strong> ' . ($data['contact']['social'] ?? 'N/A') . '</p>';

        // نمایش سوالات و پاسخ‌ها
        echo '<h2>سوالات و پاسخ‌ها:</h2>';
        // اگر سوالات پرامپت را ذخیره کرده‌ایم اینجا نمایش می‌دهیم
        echo '</div>';
    } else {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
    }
}

add_action('admin_menu', function () {
    add_submenu_page('shec-form', 'جزئیات داده‌ها', 'جزئیات داده‌ها', 'manage_options', 'shec-form-data', 'shec_display_user_details');
});

// نمایش تنظیمات صفحه
function shec_display_settings() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['shec_api_key'])) {
            update_option('shec_api_key', sanitize_text_field($_POST['shec_api_key']));
        }
        if (isset($_POST['shec_sms_api'])) {
            update_option('shec_sms_api', sanitize_text_field($_POST['shec_sms_api']));
        }
        if (isset($_POST['shec_telegram_api'])) {
            update_option('shec_telegram_api', sanitize_text_field($_POST['shec_telegram_api']));
        }
        echo '<div class="updated"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
    }

    $api_key = get_option('shec_api_key', '');
    $sms_api = get_option('shec_sms_api', '');
    $telegram_api = get_option('shec_telegram_api', '');

    echo '<div class="wrap">';
    echo '<h1>تنظیمات افزونه</h1>';
    echo '<form method="POST">';
    echo '<h2>تنظیمات API OpenAI</h2>';
    echo '<input type="text" name="shec_api_key" value="' . esc_attr($api_key) . '" />';
    echo '<h2 style="margin-top:70px;">پنل SMS</h2>';
    echo '<input type="text" name="shec_sms_api" value="' . esc_attr($sms_api) . '" />';
    echo '<h2 style="margin-top:70px;">ربات تلگرام</h2>';
    echo '<input type="text" name="shec_telegram_api" value="' . esc_attr($telegram_api) . '" />';
    echo '<input type="submit" value="ذخیره" class="button-primary" />';
    echo '</form>';
    echo '</div>';
}
