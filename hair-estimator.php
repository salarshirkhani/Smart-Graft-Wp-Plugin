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

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) تعریف ثابت‌ها
define( 'SHEC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHEC_URL', plugin_dir_url( __FILE__ ) );

require_once SHEC_PATH . 'includes/helpers.php';
require_once SHEC_PATH . 'includes/graft-estimator/ajax-handlers.php';
require_once SHEC_PATH . 'includes/admin/admin-hair.php';
