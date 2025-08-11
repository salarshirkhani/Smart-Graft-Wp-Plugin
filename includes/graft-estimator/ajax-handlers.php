<?php
/**
 * All AJAX Handlers for Smart Hair Graft Calculator
 * Version: 1.0.0
 */


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


