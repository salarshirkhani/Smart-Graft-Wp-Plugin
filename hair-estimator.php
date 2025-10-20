<?php
/**
 * Plugin Name: Smart Hair Graft Calculator
 * Plugin URI:  https://github.com/salarshirkhani/Smart-Hair-Graft-Calculator
 * Description: Smart hair graft estimator with AJAX & OpenAI, provided as a WordPress shortcode.
 * Version:     1.5.1
 * Author:      Salar ShirKhani
 * Text Domain: smart-hair-calculator
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

/* ---------------------------------
 * Constants
 * --------------------------------- */
define('SHEC_PLUGIN_FILE', __FILE__);
if (!defined('SHEC_PATH')) define('SHEC_PATH', plugin_dir_path(__FILE__));
if (!defined('SHEC_URL'))  define('SHEC_URL',  plugin_dir_url(__FILE__));

/* ---------------------------------
 * i18n: load text domain (ONE place)
 * --------------------------------- */
add_action('plugins_loaded', function () {
  // loads .../languages/smart-hair-calculator-<locale>.mo
  load_plugin_textdomain('smart-hair-calculator', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/* ---------------------------------
 * Create default pages (form + result)
 * --------------------------------- */
function shec_maybe_create_pages() {
  // Calculator
  $p1 = get_page_by_path('hair-graft-calculator', OBJECT, 'page');
  if (!$p1) {
    wp_insert_post([
      'post_title'   => 'Hair Graft Calculator',
      'post_name'    => 'hair-graft-calculator',
      'post_content' => '[smart_hair_calculator]',
      'post_status'  => 'publish',
      'post_type'    => 'page',
    ]);
  } elseif ($p1->post_status === 'trash') {
    wp_untrash_post($p1->ID);
  }

  // Result
  $p2 = get_page_by_path('hair-result', OBJECT, 'page');
  if (!$p2) {
    wp_insert_post([
      'post_title'   => 'Hair Result',
      'post_name'    => 'hair-result',
      'post_content' => '[smart_hair_result]',
      'post_status'  => 'publish',
      'post_type'    => 'page',
    ]);
  } elseif ($p2->post_status === 'trash') {
    wp_untrash_post($p2->ID);
  }
}

/* ---------------------------------
 * Activation: tables + pages + rewrite
 * --------------------------------- */
register_activation_hook(SHEC_PLUGIN_FILE, 'shec_activate_plugin');
function shec_activate_plugin() {
  if (is_multisite() && !empty($_GET['networkwide'])) {
    $site_ids = get_sites(['fields' => 'ids']);
    foreach ($site_ids as $blog_id) {
      switch_to_blog($blog_id);
      shec__create_tables();
      shec_maybe_create_pages();
      restore_current_blog();
    }
  } else {
    shec__create_tables();
    shec_maybe_create_pages();
  }
  if (function_exists('flush_rewrite_rules')) flush_rewrite_rules(false);
}

/* ---------------------------------
 * DB table: shec_users
 * --------------------------------- */
function shec__create_tables() {
  global $wpdb;
  $table   = $wpdb->prefix . 'shec_users';
  $collate = $wpdb->get_charset_collate();
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $sql = "CREATE TABLE {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    wp_user_id BIGINT UNSIGNED DEFAULT NULL,
    data LONGTEXT NOT NULL,
    created DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY wp_user_id (wp_user_id)
  ) $collate;";

  dbDelta($sql); // safe on subsequent runs
}

/* ---------------------------------
 * shec_links + public route + flush
 * --------------------------------- */
register_activation_hook(SHEC_PLUGIN_FILE, 'shec_activate_tokens_links');
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

  shec_register_public_result_route();
  flush_rewrite_rules();
}

// Deactivation: flush rewrite rules
register_deactivation_hook(SHEC_PLUGIN_FILE, function(){
  flush_rewrite_rules();
});

// Public route (optional): /r/TOKEN
add_action('init', 'shec_register_public_result_route');
function shec_register_public_result_route(){
  add_rewrite_rule('^r/([A-Za-z0-9_-]{8,32})/?$', 'index.php?shec_token=$matches[1]', 'top');
}
add_filter('query_vars', function($vars){
  $vars[] = 'shec_token';
  return $vars;
});

/* ---------------------------------
 * Front assets (RTL-aware CSS + i18n JS)
 * --------------------------------- */
add_action('wp_enqueue_scripts', 'shec_enqueue_assets');
function shec_enqueue_assets() {
  wp_enqueue_script('jquery');

  // Toastr
  wp_enqueue_style('toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', [], '2.1.4');
  wp_enqueue_script('toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', [], '2.1.4', true);

  // PDF deps
  wp_enqueue_script('html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', [], '1.4.1', true);
  wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true);

  // Lottie (optional)
  wp_enqueue_script('dotlottie', 'https://unpkg.com/@lottiefiles/dotlottie-wc/dist/dotlottie-wc.umd.js', [], null, true);

  // --- Styles (use CSS, not SCSS) ---
  // Make sure these files exist:
  // public/assets/css/style.css
  // public/assets/css/style-rtl.css
  wp_register_style(
    'shec-style',
    SHEC_URL . 'public/assets/scss/style.css',
    [],
    '1.5.1'
  );
  // Tell WP to REPLACE with -rtl in RTL locales
  wp_style_add_data('shec-style', 'rtl', 'replace');
  wp_enqueue_style('shec-style');

  // --- Script (translation-ready) ---
  wp_register_script(
    'shec-form-js',
    SHEC_URL . 'public/assets/js/form.js',
    ['jquery', 'wp-i18n', 'toastr-js', 'jspdf', 'html2canvas', 'dotlottie'],
    '1.5.1',
    true
  );

  // Attach translations for JS (needs JSON files in /languages)
  // File pattern: smart-hair-calculator-<locale>-<hash>.json
  wp_set_script_translations(
    'shec-form-js',
    'smart-hair-calculator',
    plugin_dir_path(__FILE__) . 'languages'
  );

  // Localized data
  wp_localize_script('shec-form-js', 'shec_ajax', [
    'url'           => admin_url('admin-ajax.php'),
    'nonce'         => wp_create_nonce('shec_nonce'),
    'img_path'      => SHEC_URL . 'public/assets/img/',
    'max_upload_mb' => (int) floor(wp_max_upload_size() / 1048576),
  ]);

  wp_enqueue_script('shec-form-js');
}

/* ---------------------------------
 * Debug logger
 * --------------------------------- */
if (!function_exists('shec_dbg_on')) {
  function shec_dbg_on() {
    if (defined('SHEC_DEBUG') && SHEC_DEBUG) return true;
    if (!empty($_REQUEST['shec_debug'])) return true;
    return (bool) get_option('shec_debug', false);
  }
}
if (!function_exists('shec_log')) {
  function shec_log($tag, $payload = null) {
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
  shec_log('init', ['uri' => ($_SERVER['REQUEST_URI'] ?? ''), 'user' => get_current_user_id()]);
});

/* ---------------------------------
 * Load rest of plugin files
 * --------------------------------- */
require_once SHEC_PATH . 'includes/helpers.php';
require_once SHEC_PATH . 'includes/graft-estimator/ajax-handlers.php';
require_once SHEC_PATH . 'includes/admin/admin-hair.php';
require_once SHEC_PATH . 'includes/tools/telegram.php';
require_once SHEC_PATH . 'includes/tools/sms.php';
