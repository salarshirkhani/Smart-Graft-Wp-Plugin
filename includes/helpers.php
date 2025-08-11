<?php 
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
        'img_path' => plugins_url('../public/assets/img/', __FILE__),
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
