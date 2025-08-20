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

  if (!$page) {
    $page_id = wp_insert_post([
      'post_title'   => 'Hair Graft Calculator',
      'post_name'    => $slug,
      'post_content' => '[smart_hair_calculator]',
      'post_status'  => 'publish',
      'post_type'    => 'page',
      'post_author'  => get_current_user_id() ?: 1,
      'comment_status' => 'closed',
      'ping_status'    => 'closed',
    ]);
  } else {
    // اگر برگه هست ولی شورتکد ندارد، اضافه‌اش کن
    if (strpos($page->post_content, '[smart_hair_calculator]') === false) {
      wp_update_post([
        'ID' => $page->ID,
        'post_content' => trim($page->post_content . "\n\n[smart_hair_calculator]")
      ]);
    }
  }
}
// 1) تعریف ثابت‌ها
define( 'SHEC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHEC_URL', plugin_dir_url( __FILE__ ) );


require_once SHEC_PATH . 'includes/helpers.php';
require_once SHEC_PATH . 'includes/graft-estimator/ajax-handlers.php';
require_once SHEC_PATH . 'includes/admin/admin-hair.php';