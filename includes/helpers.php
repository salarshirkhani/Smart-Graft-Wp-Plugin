<?php 

// 3) لود فایل‌های استایل و اسکریپت با nonce برای امنیت
add_action( 'wp_enqueue_scripts', 'shec_enqueue_assets' );
function shec_enqueue_assets() {
    wp_enqueue_script('jquery');

    // toastr
    wp_enqueue_style( 'toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css' );
    wp_enqueue_script( 'toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', [], null, true );

    // jsPDF برای دانلود PDF
    wp_enqueue_script(
        'html2canvas',
        'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
        array(),
        '1.4.1',
        true
    );

    wp_enqueue_script(
        'jspdf',
        'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
        array(),
        '2.5.1',
        true
    );

    wp_enqueue_script(
        'dotlottie',
        'https://unpkg.com/@lottiefiles/dotlottie-wc/dist/dotlottie-wc.umd.js',
        [],
        null,
        true
    );

    // استایل و اسکریپت خود افزونه
    wp_enqueue_style( 'shec-style', SHEC_URL . 'public/assets/scss/style.css' );
    wp_enqueue_script( 'shec-form-js', SHEC_URL . 'public/assets/js/form.js', ['jquery','toastr-js','jspdf','html2canvas','dotlottie'], '1.0.1', true );

    // فقط یک‌بار localize
    wp_localize_script('shec-form-js', 'shec_ajax', [
        'url'      => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('shec_nonce'),
        'img_path' => plugins_url('../public/assets/img/', __FILE__),
        'max_upload_mb' => (int) floor(wp_max_upload_size()/1048576),
    ]);
}

if ( ! function_exists('shec_is_localhost') ) {
    function shec_is_localhost() {
        $h = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        return in_array($h, ['localhost', '127.0.0.1'], true);
    }
}

// minute throttle counter
if (!function_exists('shec_bump_minute_counter')) {
  function shec_bump_minute_counter($key, $ttl = 120) {
    $count = (int) get_transient($key);
    set_transient($key, $count + 1, $ttl);
    return $count + 1;
  }
}
if (!function_exists('shec_get_minute_counter')) {
  function shec_get_minute_counter($key) { return (int) get_transient($key); }
}

// simple locks
if (!function_exists('shec_acquire_lock')) {
  function shec_acquire_lock($lock_key, $ttl = 30) {
    if (get_transient($lock_key)) return false;
    set_transient($lock_key, 1, $ttl);
    return true;
  }
}
if (!function_exists('shec_release_lock')) {
  function shec_release_lock($lock_key) { delete_transient($lock_key); }
}

// 429 circuit breaker
if (!function_exists('shec_rate_limited_until')) {
  function shec_rate_limited_until() { return (int) get_transient('shec_ai_block_until') ?: 0; }
}
if (!function_exists('shec_set_rate_limit_block')) {
  function shec_set_rate_limit_block($seconds) {
    $until = time() + max(60, min((int)$seconds, 600));
    set_transient('shec_ai_block_until', $until, $until - time());
    return $until;
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
            .site-header, .site-footer, .wp-admin-bar, .header, .footer ,.entry-title {
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


//-------------------------------------------------AI---------------------------------------------------
function shec_check_nonce() {
  // ⬇️ برای لوکال، نانس را بای‌پس کن (می‌تونی بعداً برداریش)
  $host = $_SERVER['HTTP_HOST'] ?? '';
  if (in_array($host, ['localhost','127.0.0.1'], true)) {
    return;
  }

  $nonce = $_POST['_nonce'] ?? '';
  if (!wp_verify_nonce($nonce, 'shec_nonce')) {
    error_log("[SHEC][NONCE] FAIL field='_nonce' value='{$nonce}'");
    // به‌جای wp_die(-1) -> 403 JSON برگردونیم تا کنسول واضح ببینه
    wp_send_json_error(['message' => 'Invalid nonce'], 403);
  }
}

// ===== [必] DB helpers: اگر قبلاً داری، همونا بمونه
if (!function_exists('shec_table')) {
  function shec_table() { global $wpdb; return $wpdb->prefix.'shec_users'; }
}
if (!function_exists('shec_get_data')) {
  function shec_get_data($user_id){
    global $wpdb;
    $json = $wpdb->get_var($wpdb->prepare("SELECT data FROM ".shec_table()." WHERE wp_user_id=%d",$user_id));
    return $json ? json_decode($json, true) : [];
  }
}
if (!function_exists('shec_update_data')) {
  function shec_update_data($user_id, array $data){
    global $wpdb;
    return $wpdb->update(shec_table(), ['data'=>wp_json_encode($data)], ['wp_user_id'=>$user_id], ['%s'], ['%d']);
  }
}

// ===== OpenAI helpers (بدون کلید هم کرش نمی‌کند)
if (!function_exists('shec_openai_api_key')) {
  function shec_openai_api_key() {
    return trim((string) get_option('shec_api_key',''));
  }
}
if (!function_exists('shec_openai_chat')) {
function shec_openai_chat(array $messages, array $opts = []) {
  $api_key = trim((string)get_option('shec_api_key',''));
  if (!$api_key) return ['ok'=>false, 'error'=>'API key missing', 'http_code'=>0];

  $models = $opts['models'] ?? [
    $opts['model'] ?? 'gpt-4o-mini',
    'gpt-4o',
    'gpt-4.1-mini'
  ];
  $is_local = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']);

  $last_err = 'unknown'; $last_code = 0;

  foreach ($models as $model) {
    $body = [
      'model'           => $model,
      'temperature'     => $opts['temperature'] ?? 0.2,
      'response_format' => ['type'=>'json_object'],
      'messages'        => $messages,
    ];
    $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
      'headers'  => ['Authorization'=>'Bearer '.$api_key,'Content-Type'=>'application/json'],
      'body'     => wp_json_encode($body),
      'timeout'  => 45,
      'sslverify'=> $is_local ? false : true, // لوکال: مشکل SSL حل
    ]);
    if (is_wp_error($res)) { $last_err=$res->get_error_message(); error_log('[SHEC][OPENAI] '.$last_err); continue; }
    $code = wp_remote_retrieve_response_code($res);
    $raw  = wp_remote_retrieve_body($res);
    $json = json_decode($raw, true);
    if ($code >= 400) {
      $msg = $json['error']['message'] ?? ("HTTP ".$code);
      $last_err=$msg; $last_code=$code;
      error_log("[SHEC][OPENAI] HTTP $code model=$model msg=$msg");
      if (stripos($msg,'model')!==false || stripos($msg,'does not exist')!==false) continue; // امتحان مدل بعدی
      break;
    }
    $content = $json['choices'][0]['message']['content'] ?? '';
    return ['ok'=>true,'content'=>$content,'raw'=>$json,'http_code'=>$code,'model'=>$model];
  }
  return ['ok'=>false,'error'=>$last_err,'http_code'=>$last_code];
}


}
if (!function_exists('shec_json_decode_safe')) {
  function shec_json_decode_safe($str){
    if (!is_string($str)) return null;
    $str = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($str));
    $data = json_decode($str, true);
    return is_array($data) ? $data : null;
  }
}

//ADMIN STYLES
// فقط روی صفحات افزونه استایل ادمین لود می‌شود
add_action('admin_enqueue_scripts', function ($hook) {
    if (empty($_GET['page'])) return;
    $pages = ['shec-form','shec-settings','shec-form-data'];
    if (!in_array($_GET['page'], $pages, true)) return;

    $css_url = plugin_dir_url( dirname(__FILE__) ) . 'includes/admin/admin.css';
    wp_enqueue_style('shec-admin', $css_url, [], '1.0.0');
});

