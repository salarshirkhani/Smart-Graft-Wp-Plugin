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
    wp_enqueue_style( 'toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css' );
    wp_enqueue_script( 'toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js' );

    wp_enqueue_style( 'shec-style', SHEC_URL . 'public/assets/scss/style.css' );

    wp_enqueue_script( 'shec-form-js', SHEC_URL . 'public/assets/js/form.js', ['jquery','toastr-js'], '1.0.0', true );
    wp_localize_script( 'shec-form-js', 'shec_ajax', [
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'shec_nonce' ),
    ] );

    wp_localize_script('shec-form-js', 'shec_ajax', [
        'url'      => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('shec_nonce'),
        'img_path' => plugins_url('public/assets/img/', __FILE__), // ✅ مسیر عکس‌ها برای JS
    ]);
}



// 5) شورت‌کد برای نمایش فرم
add_shortcode( 'smart_hair_calculator', function() {
    ob_start();
    include SHEC_PATH . 'templates/form-template.php';
    return ob_get_clean();
});

// ================= STEP 1 =================
add_action( 'wp_ajax_shec_step1', 'shec_handle_step1' );
add_action( 'wp_ajax_nopriv_shec_step1', 'shec_handle_step1' );
function shec_handle_step1() {
    check_ajax_referer('shec_nonce','_nonce');
    global $wpdb;

    $gender     = sanitize_text_field($_POST['gender']);
    $age        = sanitize_text_field($_POST['age']);
    $confidence = sanitize_text_field($_POST['confidence']);

    // بررسی اینکه تمام داده‌ها ارسال شده‌اند
    if ( ! $gender || ! $age ) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
    }

    // ایجاد شناسه یکتا برای کاربر
    $user_id = get_current_user_id(); // اگر یوزر لاگین کرده باشد، شناسه او را می‌گیرد
    if (!$user_id) {
        // اگر کاربر لاگین نکرده باشد، یک شناسه تصادفی عددی ایجاد کن
        $user_id = $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}shec_users") + 1; 
    }
    error_log("User ID: " . $user_id); // لاگ گرفتن از شناسه یکتا
    // ذخیره اطلاعات در جدول shec_users
    $wpdb->insert( $wpdb->prefix . 'shec_users', [
        'wp_user_id' => $user_id, // ذخیره شناسه یکتا در فیلد wp_user_id
        'data' => wp_json_encode([
            'gender'     => $gender,
            'age'        => $age,
            'confidence' => $confidence
        ])
    ] );

    wp_send_json_success(['user_id' => $user_id]); // ارسال شناسه یکتا برای استفاده در مراحل بعد
}

// ================= STEP 2 =================
add_action( 'wp_ajax_shec_step2', 'shec_handle_step2' );
add_action( 'wp_ajax_nopriv_shec_step2', 'shec_handle_step2' );
function shec_handle_step2() {
    check_ajax_referer('shec_nonce','_nonce');
    global $wpdb;

    $user_id      = intval($_POST['user_id']); // دریافت user_id از درخواست
    $pattern      = sanitize_text_field($_POST['loss_pattern']); // دریافت الگوی ریزش مو

    if ( $user_id <= 0 || empty( $pattern ) ) {
        wp_send_json_error( 'اطلاعات مرحله ۲ ناقص است' );
    }

    // جستجوی داده‌ها با استفاده از wp_user_id (فیلد صحیح)
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id = %s", $user_id
    ));

    // اگر داده‌ها وجود دارند، الگوی ریزش را اضافه کنید
    $data = $existing ? json_decode( $existing, true ) : [];
    $data['loss_pattern'] = $pattern;

    // به‌روزرسانی داده‌ها در دیتابیس
    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($data)],
        ['wp_user_id' => $user_id], // تغییر از 'id' به 'wp_user_id'
        ['%s'],
        ['%s']
    );

    wp_send_json_success();
}


// ================= STEP 3 =================
add_action( 'wp_ajax_shec_step3', 'shec_handle_step3' );
add_action( 'wp_ajax_nopriv_shec_step3', 'shec_handle_step3' );
function shec_handle_step3(){
    check_ajax_referer('shec_nonce','_nonce');
    global $wpdb;

    $user_id = intval($_POST['user_id']);
    $position = sanitize_text_field($_POST['position']);

    if ( ! $user_id || empty($_FILES) ) {
        wp_send_json_error(['message' => 'فایل یا کاربر معتبر نیست.']);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload($_FILES[array_key_first($_FILES)], ['test_form' => false]);

    if ( isset($uploaded['error']) ) {
        wp_send_json_error(['message' => $uploaded['error']]);
    }

    $existing = json_decode($wpdb->get_var("SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id={$user_id}"), true);
    $existing['uploads'][$position] = $uploaded['url'];

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($existing)],
        ['id'   => $user_id]
    );

    wp_send_json_success(['file' => $uploaded['url']]);
}

// ================= STEP 4 =================
add_action( 'wp_ajax_shec_step4', 'shec_handle_step4' );
add_action( 'wp_ajax_nopriv_shec_step4', 'shec_handle_step4' );
function shec_handle_step4(){
    check_ajax_referer('shec_nonce','_nonce');
    global $wpdb;

    $user_id = intval($_POST['user_id']);
    if ( ! $user_id ) wp_send_json_error(['message' => 'کاربر معتبر نیست.']);

    $medical_data = array_map('sanitize_text_field', $_POST);
    unset($medical_data['_nonce'], $medical_data['action'], $medical_data['user_id']);

    $existing = json_decode($wpdb->get_var("SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id={$user_id}"), true);
    $existing['medical'] = $medical_data;

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($existing)],
        ['id'   => $user_id]
    );

    wp_send_json_success();
}

// ================= STEP 5 =================
add_action( 'wp_ajax_shec_step5', 'shec_handle_step5' );
add_action( 'wp_ajax_nopriv_shec_step5', 'shec_handle_step5' );
function shec_handle_step5(){
    check_ajax_referer('shec_nonce','_nonce');
    global $wpdb;

    $user_id = intval($_POST['user_id']);
    if ( ! $user_id ) wp_send_json_error(['message' => 'کاربر معتبر نیست.']);

    // گرفتن اطلاعات از دیتابیس با استفاده از wp_user_id
    $existing_data = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM {$wpdb->prefix}shec_users WHERE wp_user_id = %d", $user_id ) );

    // بررسی اینکه داده‌ها وجود دارند
    if ( is_null( $existing_data ) ) {
        error_log( "No data found for user_id: " . $user_id );
        wp_send_json_error( 'داده‌ای برای این کاربر پیدا نشد' );
    } else {
        // تبدیل داده‌ها به آرایه
        $existing = json_decode( $existing_data, true );
    }
    error_log( "Data retrieved for user_id: " . $user_id . " - " . print_r( $existing, true ) );
    // اضافه کردن داده‌های تماس به موجود
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name  = sanitize_text_field($_POST['last_name']);
    $state      = sanitize_text_field($_POST['state']);
    $city       = sanitize_text_field($_POST['city']);
    $mobile     = sanitize_text_field($_POST['mobile']);
    $social     = sanitize_text_field($_POST['social']);

    if (empty($first_name) || empty($last_name) || empty($state) || empty($city) || empty($mobile) || empty($social)) {
        wp_send_json_error(['message' => 'تمامی فیلدها باید پر شوند.']);
    }

    // ذخیره اطلاعات تماس به داده‌های موجود
    $existing['contact'] = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'state'      => $state,
        'city'       => $city,
        'mobile'     => $mobile,
        'social'     => $social
    ];

    // بروزرسانی اطلاعات در دیتابیس
    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($existing)],
        ['wp_user_id' => $user_id] // استفاده از wp_user_id در به‌روزرسانی
    );

    wp_send_json_success([
        'user' => $existing
    ]);
}

function hide_header_footer_on_shec_page() {
    if (is_page() && has_shortcode(get_post()->post_content, 'smart_hair_calculator')) {
        echo '<style>
            body {
                margin: 0;
                padding: 0;
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

// نمایش داده‌ها در صفحه ادمین
function shec_add_admin_menu() {
    add_menu_page(
        'فرم هوشمند فخرایی', // عنوان صفحه
        'فرم هوشمند فخرایی ', // عنوان منو در داشبورد
        'manage_options',     // مجوز دسترسی (ادمین)
        'shec-form',          // شناسه منو
        'shec_display_data',  // تابعی که داده‌ها را نمایش می‌دهد
        'dashicons-chart-pie', // آیکون منو
        6                     // موقعیت منو
    );

    // زیر منو تنظیمات
    add_submenu_page(
        'shec-form',
        'تنظیمات',           // عنوان صفحه زیرمنو
        'تنظیمات',           // عنوان منو
        'manage_options',     // مجوز دسترسی (ادمین)
        'shec-settings',      // شناسه صفحه زیرمنو
        'shec_display_settings' // تابعی که تنظیمات را نمایش می‌دهد
    );
}
add_action('admin_menu', 'shec_add_admin_menu');

// نمایش داده‌ها در صفحه ادمین
function shec_display_data() {
    global $wpdb;

    // گرفتن داده‌ها از جدول
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
                        <th scope="col" class="manage-column">الگوی ریزش مو</th>
                        <th scope="col" class="manage-column">سابقه پزشکی</th>
                        <th scope="col" class="manage-column">نام</th>
                        <th scope="col" class="manage-column">نام خانوادگی</th>
                        <th scope="col" class="manage-column">استان</th>
                        <th scope="col" class="manage-column">شهر</th>
                        <th scope="col" class="manage-column">شماره تلفن</th>
                        <th scope="col" class="manage-column">سوشال مدیا</th>
                        <th scope="col" class="manage-column">مرحله تکمیل</th>
                    </tr>
                </thead>
                <tbody>';

        // نمایش داده‌ها
        foreach ($results as $row) {
            $data = json_decode($row->data, true); // تبدیل JSON به آرایه

            // نمایش داده‌های مختلف
            $gender = $data['gender'] ?? 'N/A';
            $age = $data['age'] ?? 'N/A';
            $confidence = $data['confidence'] ?? 'N/A';
            $loss_pattern = $data['loss_pattern'] ?? 'N/A';
            $medical = $data['medical'] ?? [];
            $first_name = $data['contact']['first_name'] ?? 'N/A';
            $last_name = $data['contact']['last_name'] ?? 'N/A';
            $state = $data['contact']['state'] ?? 'N/A';
            $city = $data['contact']['city'] ?? 'N/A';
            $mobile = $data['contact']['mobile'] ?? 'N/A';
            $social = $data['contact']['social'] ?? 'N/A';

            // مشخص کردن اینکه کاربر چه مرحله‌ای را تکمیل کرده است
            $completion_stage = 0;
            if (isset($data['contact']['first_name']) && isset($data['contact']['last_name'])) {
                $completion_stage = 5;
            } elseif (isset($data['gender']) && isset($data['age'])) {
                $completion_stage = 3;
            } else {
                $completion_stage = 1;
            }

            // رنگ‌بندی بر اساس مرحله تکمیل
            $stage_class = '';
            if ($completion_stage < 5) {
                $stage_class = 'style="background-color: #f7f7a5;"'; // زرد برای مراحل ناتمام
            }

            echo '<tr ' . $stage_class . '>';
            echo '<td>' . $row->id . '</td>';
            echo '<td>' . $gender . '</td>';
            echo '<td>' . $age . '</td>';
            echo '<td>' . $confidence . '</td>';
            echo '<td>' . $loss_pattern . '</td>';
            echo '<td>' . (isset($medical['has_medical']) ? $medical['has_medical'] : 'N/A') . '</td>';
            echo '<td>' . $first_name . '</td>';
            echo '<td>' . $last_name . '</td>';
            echo '<td>' . $state . '</td>';
            echo '<td>' . $city . '</td>';
            echo '<td>' . $mobile . '</td>';
            echo '<td>' . $social . '</td>';
            echo '<td>' . $completion_stage . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>داده‌ای یافت نشد.</p>';
    }
    echo '</div>';
}

// صفحه تنظیمات
function shec_display_settings() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['shec_api_key'])) {
        // ذخیره‌سازی کلید API در دیتابیس
        update_option('shec_api_key', sanitize_text_field($_POST['shec_api_key']));
        echo '<div class="updated"><p>کلید API با موفقیت ذخیره شد.</p></div>';
    }

    // خواندن کلید API ذخیره‌شده (در صورت وجود)
    $api_key = get_option('shec_api_key', '');

    echo '<div class="wrap">';
    echo '<h1>تنظیمات API</h1>';
    echo '<form method="POST">';
    echo '<label for="shec_api_key">کلید API OpenAI:</label>';
    echo '<input type="text" id="shec_api_key" name="shec_api_key" value="' . esc_attr($api_key) . '" />';
    echo '<input type="submit" value="ذخیره" class="button-primary" />';
    echo '</form>';
    echo '</div>';
}

// ثبت تنظیمات API
function shec_register_settings() {
    register_setting('shec_settings_group', 'shec_openai_api_key');
}

add_action('admin_init', 'shec_register_settings');
