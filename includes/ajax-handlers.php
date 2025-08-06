<?php
/**
 * All AJAX Handlers for Smart Hair Graft Calculator
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * STEP 1: ذخیره اطلاعات اولیه (جنسیت، سن، اعتماد به نفس)
 */
add_action( 'wp_ajax_shec_step1', 'shec_handle_step1' );
add_action( 'wp_ajax_nopriv_shec_step1', 'shec_handle_step1' );
function shec_handle_step1() {
    check_ajax_referer( 'shec_nonce', '_nonce' );
    global $wpdb;

    $gender     = sanitize_text_field( $_POST['gender'] ?? '' );
    $age        = sanitize_text_field( $_POST['age'] ?? '' );
    $confidence = sanitize_text_field( $_POST['confidence'] ?? '' );

    if ( empty( $gender ) || empty( $age ) ) {
        wp_send_json_error( 'اطلاعات ناقص است' );
    }

    $wpdb->insert(
        $wpdb->prefix . 'shec_users',
        [ 'data' => wp_json_encode( compact( 'gender', 'age', 'confidence' ) ) ],
        [ '%s' ]
    );
    $user_id = $wpdb->insert_id;

    wp_send_json_success( [ 'user_id' => $user_id ] );
}

/**
 * STEP 2: ذخیره الگوی ریزش مو
 */
add_action( 'wp_ajax_shec_step2', 'shec_handle_step2' );
add_action( 'wp_ajax_nopriv_shec_step2', 'shec_handle_step2' );
function shec_handle_step2() {
    check_ajax_referer( 'shec_nonce', '_nonce' );
    global $wpdb;

    $user_id  = intval( $_POST['user_id'] ?? 0 );
    $pattern  = sanitize_text_field( $_POST['loss_pattern'] ?? '' );

    if ( $user_id <= 0 || empty( $pattern ) ) {
        wp_send_json_error( 'اطلاعات مرحله ۲ ناقص است' );
    }

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE id = %d",
        $user_id
    ));
    $data = $existing ? json_decode( $existing, true ) : [];
    $data['loss_pattern'] = $pattern;

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        [ 'data' => wp_json_encode( $data ) ],
        [ 'id' => $user_id ],
        [ '%s' ],
        [ '%d' ]
    );

    wp_send_json_success();
}

/**
 * STEP 3: آپلود تصاویر
 */
add_action( 'wp_ajax_shec_step3', 'shec_handle_step3' );
add_action( 'wp_ajax_nopriv_shec_step3', 'shec_handle_step3' );
function shec_handle_step3() {
    check_ajax_referer( 'shec_nonce', '_nonce' );
    global $wpdb;

    $user_id = intval( $_POST['user_id'] ?? 0 );
    if ( $user_id <= 0 ) {
        wp_send_json_error( 'شناسه کاربر معتبر نیست' );
    }

    if ( empty( $_FILES ) ) {
        wp_send_json_error( 'هیچ فایلی ارسال نشده' );
    }

    $upload_overrides = [ 'test_form' => false ];
    $uploaded_files   = [];

    foreach ( $_FILES as $key => $file ) {
        $movefile = wp_handle_upload( $file, $upload_overrides );
        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $uploaded_files[$key] = $movefile['url'];
        }
    }

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE id = %d",
        $user_id
    ));
    $data = $existing ? json_decode( $existing, true ) : [];
    $data['uploaded_files'] = $uploaded_files;

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        [ 'data' => wp_json_encode( $data ) ],
        [ 'id' => $user_id ],
        [ '%s' ],
        [ '%d' ]
    );

    wp_send_json_success( [ 'files' => $uploaded_files ] );
}

/**
 * STEP 4: سوالات پزشکی
 */
add_action( 'wp_ajax_shec_step4', 'shec_handle_step4' );
add_action( 'wp_ajax_nopriv_shec_step4', 'shec_handle_step4' );
function shec_handle_step4() {
    check_ajax_referer( 'shec_nonce', '_nonce' );
    global $wpdb;

    $user_id      = intval( $_POST['user_id'] ?? 0 );
    $has_medical  = sanitize_text_field( $_POST['has_medical'] ?? '' );
    $medicals     = sanitize_text_field( $_POST['medical_details'] ?? '' );
    $has_meds     = sanitize_text_field( $_POST['has_meds'] ?? '' );
    $meds_details = sanitize_text_field( $_POST['meds_details'] ?? '' );

    if ( $user_id <= 0 ) {
        wp_send_json_error( 'شناسه کاربر معتبر نیست' );
    }

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE id = %d",
        $user_id
    ));
    $data = $existing ? json_decode( $existing, true ) : [];
    $data['medical'] = [
        'has_medical'  => $has_medical,
        'medical_details' => $medicals,
        'has_meds'     => $has_meds,
        'meds_details' => $meds_details,
    ];

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        [ 'data' => wp_json_encode( $data ) ],
        [ 'id' => $user_id ],
        [ '%s' ],
        [ '%d' ]
    );

    wp_send_json_success();
}

/**
 * STEP 5: اطلاعات تماس و AI Result
 */
add_action( 'wp_ajax_shec_step5', 'shec_handle_step5' );
add_action( 'wp_ajax_nopriv_shec_step5', 'shec_handle_step5' );
function shec_handle_step5() {
    check_ajax_referer( 'shec_nonce', '_nonce' );
    global $wpdb;

    $user_id   = intval( $_POST['user_id'] ?? 0 );
    $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
    $last_name  = sanitize_text_field( $_POST['last_name'] ?? '' );
    $city       = sanitize_text_field( $_POST['city'] ?? '' );
    $state      = sanitize_text_field( $_POST['state'] ?? '' );
    $phone      = sanitize_text_field( $_POST['phone'] ?? '' );
    $email      = sanitize_email( $_POST['email'] ?? '' );

    if ( $user_id <= 0 ) {
        wp_send_json_error( 'شناسه کاربر معتبر نیست' );
    }

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT data FROM {$wpdb->prefix}shec_users WHERE id = %d",
        $user_id
    ));
    $data = $existing ? json_decode( $existing, true ) : [];

    $data['contact'] = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'city'       => $city,
        'state'      => $state,
        'phone'      => $phone,
        'email'      => $email,
    ];

    // اینجا می‌تونی کد AI رو اضافه کنی
    $data['ai_result'] = [
        'method'     => 'FIT',
        'graft_count'=> rand( 1500, 3000 ),
        'analysis'   => 'توضیحات تحلیلی هوش مصنوعی در اینجا قرار می‌گیرد.'
    ];

    $wpdb->update(
        $wpdb->prefix . 'shec_users',
        [ 'data' => wp_json_encode( $data ) ],
        [ 'id' => $user_id ],
        [ '%s' ],
        [ '%d' ]
    );

    wp_send_json_success( [
        'ai_result' => $data['ai_result'],
        'user' => $data['contact']
    ] );
}
