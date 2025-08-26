<?php
/**
 * Plugin Name: Smart Hair Graft Calculator
 * Plugin URI:  https://github.com/salarshirkhani/Smart-Hair-Graft-Calculator
 * Description: ابزار هوشمند محاسبه تعداد تار مو با AJAX و OpenAI، حالا به عنوان یک شورت‌کد وردپرس.
 * Version:     1.0.0
 * Author:      Salar ShirKhani
 * Text Domain: smart-hair-calculator
 * Domain Path: /languages
 */


if (!defined('ABSPATH')) exit;

define('SHEC_PLUGIN_FILE', __FILE__);

// bootstrap.php (فایل اصلی افزونه)
if (!defined('SHEC_PATH')) define('SHEC_PATH', plugin_dir_path(__FILE__));
if (!defined('SHEC_URL'))  define('SHEC_URL',  plugin_dir_url(__FILE__));

add_action('plugins_loaded', function(){ error_log('[SHEC] plugins_loaded + ajax-handlers loaded'); });

register_activation_hook(SHEC_PLUGIN_FILE, 'shec_activate_plugin');

function shec_activate_plugin() {
  if (is_multisite() && !empty($_GET['networkwide'])) {
    $site_ids = get_sites(['fields'=>'ids']);
    foreach ($site_ids as $blog_id) {
      switch_to_blog($blog_id);
      shec__create_tables_and_page();
      restore_current_blog();
    }
  } else {
    shec__create_tables_and_page();
  }

  if (function_exists('flush_rewrite_rules')) flush_rewrite_rules(false);
}

function shec__create_tables_and_page(){
  global $wpdb;
  $table   = $wpdb->prefix . 'shec_users';
  $collate = $wpdb->get_charset_collate();

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  // اگر جدول وجود ندارد، بساز
  $exists = $wpdb->get_var( $wpdb->prepare(
    "SHOW TABLES LIKE %s", $table
  ) );

  $sql = "CREATE TABLE {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    wp_user_id BIGINT UNSIGNED DEFAULT NULL,
    data LONGTEXT NOT NULL,
    created DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY wp_user_id (wp_user_id)
  ) $collate;";

  if ($exists !== $table) {
    dbDelta($sql);
  } else {
    // اگر وجود دارد هم dbDelta امن است (برای آپدیت اسکیمای بعدی)
    dbDelta($sql);
  }

  // برگه را اگر نبود بساز/از زباله‌دان خارج کن
  $slug = 'hair-graft-calculator';
  $page = get_page_by_path($slug, OBJECT, 'page');

  if ($page && $page->post_status === 'trash') {
    wp_untrash_post($page->ID);
  }

  if (!function_exists('shec_maybe_create_pages')) {
    function shec_maybe_create_pages(){
      // فرم
      $p1 = get_page_by_path('hair-graft-calculator');
      if (!$p1) {
        wp_insert_post([
          'post_title'   => 'Hair Graft Calculator',
          'post_name'    => 'hair-graft-calculator',
          'post_content' => '[smart_hair_calculator]',
          'post_status'  => 'publish',
          'post_type'    => 'page',
        ]);
      }
      // نتیجه
      $p2 = get_page_by_path('hair-result');
      if (!$p2) {
        wp_insert_post([
          'post_title'   => 'Hair Result',
          'post_name'    => 'hair-result',
          'post_content' => '[smart_hair_result]',
          'post_status'  => 'publish',
          'post_type'    => 'page',
        ]);
      }
    }
  }
  add_action('init','shec_maybe_create_pages');

}

// ===== Activation: جدول shec_links + فلاش ریرایت =====
register_activation_hook(__FILE__, 'shec_activate_tokens_links');
function shec_activate_tokens_links() {
  global $wpdb;
  $tbl = $wpdb->prefix . 'shec_links';
  $charset = $wpdb->get_charset_collate();
  $sql = "CREATE TABLE {$tbl} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    wp_user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY token_hash (token_hash),
    KEY wp_user_id (wp_user_id)
  ) {$charset};";
  require_once ABSPATH.'wp-admin/includes/upgrade.php';
  dbDelta($sql);

  // ثبت روت و فلاش
  shec_register_public_result_route();
  flush_rewrite_rules();
}

// در Deactivation ریرایت‌ها را پاکسازی کن (اختیاری ولی خوبه)
register_deactivation_hook(__FILE__, function(){
  flush_rewrite_rules();
});

add_action('init', 'shec_register_public_result_route');
function shec_register_public_result_route(){
  add_rewrite_rule('^r/([A-Za-z0-9_-]{8,32})/?$', 'index.php?shec_token=$matches[1]', 'top');
}
add_filter('query_vars', function($vars){
  $vars[] = 'shec_token';
  return $vars;
});

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
        'img_path'      => SHEC_URL.'public/assets/img/',
        'max_upload_mb' => (int) floor(wp_max_upload_size()/1048576),
    ]);
}

// ===== Debug logger (قابل خاموش/روشن با option یا ?shec_debug=1) =====
if (!function_exists('shec_dbg_on')) {
  function shec_dbg_on() {
    if (defined('SHEC_DEBUG') && SHEC_DEBUG) return true;
    if (!empty($_REQUEST['shec_debug'])) return true;
    return (bool) get_option('shec_debug', false);
  }
}
if (!function_exists('shec_log')) {
  function shec_log($tag, $payload=null) {
    if (!shec_dbg_on()) return;
    if (!isset($GLOBALS['__shec_req'])) {
      $GLOBALS['__shec_req'] = substr(md5(uniqid('', true)), 0, 8);
    }
    $rid  = $GLOBALS['__shec_req'];
    $line = '[SHEC]['.$rid.']['.$tag.'] ';
    if ($payload !== null) {
      $line .= is_string($payload) ? $payload : wp_json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    error_log($line);
  }
}
add_action('init', function(){
  shec_log('init', ['uri'=>($_SERVER['REQUEST_URI'] ?? ''), 'user'=>get_current_user_id()]);
});


require_once SHEC_PATH . 'includes/helpers.php';
require_once SHEC_PATH . 'includes/graft-estimator/ajax-handlers.php';
require_once SHEC_PATH . 'includes/admin/admin-hair.php';
require_once SHEC_PATH . 'includes/tools/telegram.php';