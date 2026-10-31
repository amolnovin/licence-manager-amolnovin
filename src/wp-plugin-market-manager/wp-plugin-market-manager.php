<?php
/**
 * Plugin Name: سامانه فروش و لایسنس افزونه و قالب
 * Plugin URI:  https://example.com
 * Description: سامانه اختصاصی فروش، معرفی، لایسنس آنلاین، بروزرسانی و پیامک برای افزونه‌ها و قالب‌های وردپرس.
 * Version:     1.3.0
 * Author:      Arena.ai
 * Text Domain: wp-plugin-market-manager
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 */

defined('ABSPATH') || exit;

define('WPLM_VERSION', '1.3.0');
define('WPLM_FILE', __FILE__);
define('WPLM_PATH', plugin_dir_path(__FILE__));
define('WPLM_URL', plugin_dir_url(__FILE__));
define('WPLM_BASENAME', plugin_basename(__FILE__));

require_once WPLM_PATH . 'includes/Autoloader.php';
\WPLM\Autoloader::register();

register_activation_hook(__FILE__, [\WPLM\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\WPLM\Activator::class, 'deactivate']);

add_action('plugins_loaded', static function () {
    load_plugin_textdomain('wp-plugin-market-manager', false, dirname(WPLM_BASENAME) . '/languages');
    \WPLM\Plugin::instance()->boot();
});
