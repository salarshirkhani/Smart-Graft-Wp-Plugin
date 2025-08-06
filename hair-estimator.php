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
function shec_handle_step1(){
    check_ajax_referer('shec_nonce','_nonce');
    global $wpdb;

    $gender     = sanitize_text_field($_POST['gender']);
    $age        = sanitize_text_field($_POST['age']);
    $confidence = sanitize_text_field($_POST['confidence']);

    if ( ! $gender || ! $age ) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
    }

    $wpdb->insert( $wpdb->prefix . 'shec_users', [
        'data' => wp_json_encode([
            'gender'     => $gender,
            'age'        => $age,
            'confidence' => $confidence
        ])
    ] );

    wp_send_json_success(['user_id' => $wpdb->insert_id]);
}

// ================= STEP 2 =================
add_action( 'wp_ajax_shec_step2', 'shec_handle_step2' );
add_action( 'wp_ajax_nopriv_shec_step2', 'shec_handle_step2' );
function shec_handle_step2(){
    check_ajax_referer('shec_nonce','_nonce');
    global $wpdb;

    $user_id      = intval($_POST['user_id']);
    $loss_pattern = sanitize_text_field($_POST['loss_pattern']);

    if ( ! $user_id || ! $loss_pattern ) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
    }

    $existing = json_decode($wpdb->get_var("SELECT data FROM {$wpdb->prefix}shec_users WHERE id={$user_id}"), true);
    $existing['loss_pattern'] = $loss_pattern;

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        ['data' => wp_json_encode($existing)],
        ['id'   => $user_id]
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

    $existing = json_decode($wpdb->get_var("SELECT data FROM {$wpdb->prefix}shec_users WHERE id={$user_id}"), true);
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

    $existing = json_decode($wpdb->get_var("SELECT data FROM {$wpdb->prefix}shec_users WHERE id={$user_id}"), true);
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

    $contact_data = array_map('sanitize_text_field', $_POST);
    unset($contact_data['_nonce'], $contact_data['action'], $contact_data['user_id']);

    $existing = json_decode($wpdb->get_var("SELECT data FROM {$wpdb->prefix}shec_users WHERE id={$user_id}"), true);
    $existing['contact'] = $contact_data;

    // --- این قسمت رو باید با API واقعی OpenAI جایگزین کنی ---
    $ai_result = [
        'method'      => 'FIT',
        'graft_count' => 1500,
        'analysis'    => 'توضیحات تحلیل توسط AI (فعلاً تستی)'
    ];
    $existing['ai_result'] = $ai_result;

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        [
            'data' => wp_json_encode($existing),
            'wp_user_id' => get_current_user_id()
        ],
        ['id'   => $user_id]
    );

    wp_send_json_success([
        'ai_result' => wp_json_encode($ai_result),
        'user'      => $existing
    ]);
}


