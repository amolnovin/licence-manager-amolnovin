<?php
/**
 * IPPanel Core Addon Helper
 *
 * روش استفاده در افزونه جانبی:
 *
 * require_once plugin_dir_path(__FILE__) . 'includes/ippanel-core-addon-helper.php';
 *
 * IPPanel_Core_Addon_Client::boot([
 *     'slug'             => 'amolnovin-woo-inventory',
 *     'name'             => 'موجودی ووکامرس آمل نوین',
 *     'integration_code' => 'AMN-WOO-INVENTORY',
 *     'namespace'        => 'amolnovin-inventory/v1',
 * ]);
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('IPPanel_Core_Addon_Client')) {
    class IPPanel_Core_Addon_Client {
        protected static $configs = [];

        /**
         * راه‌اندازی افزونه جانبی و ثبت routeها
         */
        public static function boot($config) {
            $config = self::normalize_config($config);

            if (empty($config['slug']) || empty($config['name']) || empty($config['integration_code']) || empty($config['namespace'])) {
                return false;
            }

            self::$configs[$config['slug']] = $config;

            add_action('plugins_loaded', function () use ($config) {
                self::register_plugin($config);
            }, 20);

            add_action('rest_api_init', function () use ($config) {
                self::register_routes($config);
            });

            return true;
        }

        /**
         * رجیستر افزونه جانبی در ippanel-core
         */
        public static function register_plugin($config) {
            if (!function_exists('ippanel_register_external_plugin')) {
                return false;
            }

            $payload = [
                'slug'             => $config['slug'],
                'name'             => $config['name'],
                'integration_code' => $config['integration_code'],
                'callback_url'     => home_url('/wp-json/' . $config['namespace'] . '/receive-key'),
            ];

            return ippanel_register_external_plugin($payload);
        }

        /**
         * ثبت route برای دریافت token از ippanel-core
         */
        public static function register_routes($config) {
            register_rest_route($config['namespace'], '/receive-key', [
                'methods'             => 'POST',
                'callback'            => function (WP_REST_Request $request) use ($config) {
                    return self::receive_key($request, $config);
                },
                'permission_callback' => '__return_true',
            ]);
        }

        /**
         * دریافت token از ippanel-core و ذخیره در option اختصاصی افزونه
         */
        public static function receive_key(WP_REST_Request $request, $config) {
            $action           = sanitize_text_field($request->get_param('action'));
            $plugin_slug      = sanitize_key($request->get_param('plugin_slug'));
            $integration_code = self::normalize_integration_code($request->get_param('integration_code'));
            $api_key          = sanitize_text_field($request->get_param('api_key'));

            if ($action !== 'receive_api_key') {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'invalid action',
                ], 400);
            }

            if ($plugin_slug !== $config['slug']) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'invalid plugin slug',
                ], 400);
            }

            if ($integration_code !== $config['integration_code']) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'invalid integration code',
                ], 400);
            }

            update_option(self::token_option_name($config['slug']), $api_key);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'API key saved successfully',
            ], 200);
        }

        /**
         * نام option توکن افزونه جانبی
         */
        public static function token_option_name($plugin_slug) {
            if (function_exists('ippanel_get_external_plugin_token_option_name')) {
                return ippanel_get_external_plugin_token_option_name($plugin_slug);
            }

            return 'ippanel_api_key_' . sanitize_key($plugin_slug);
        }

        /**
         * دریافت token ذخیره‌شده
         */
        public static function get_token($plugin_slug) {
            return get_option(self::token_option_name($plugin_slug), '');
        }

        /**
         * ارسال پیامک پترن از طریق ippanel-core
         */
        public static function send_pattern_sms($plugin_slug, $mobile, $pattern_code, $params = []) {
            if (!function_exists('ippanel_send_external_sms')) {
                return new WP_Error('ippanel_core_not_loaded', 'افزونه ippanel-core در سایت فعال یا بارگذاری نشده است.');
            }

            $token = self::get_token($plugin_slug);
            if (empty($token)) {
                return new WP_Error('ippanel_core_token_missing', 'Token دسترسی افزونه جانبی از ippanel-core دریافت نشده است.');
            }

            return ippanel_send_external_sms($token, $mobile, $pattern_code, $params, 'pattern');
        }

        /**
         * ارسال پیامک متنی از طریق ippanel-core
         */
        public static function send_text_sms($plugin_slug, $mobile, $message) {
            if (!function_exists('ippanel_send_external_sms')) {
                return new WP_Error('ippanel_core_not_loaded', 'افزونه ippanel-core در سایت فعال یا بارگذاری نشده است.');
            }

            $token = self::get_token($plugin_slug);
            if (empty($token)) {
                return new WP_Error('ippanel_core_token_missing', 'Token دسترسی افزونه جانبی از ippanel-core دریافت نشده است.');
            }

            return ippanel_send_external_sms($token, $mobile, $message, [], 'text');
        }

        /**
         * alias برای ارسال پیامک ساده
         */
        public static function send_simple_sms($plugin_slug, $mobile, $message) {
            return self::send_text_sms($plugin_slug, $mobile, $message);
        }

        /**
         * بررسی دریافت شدن token برای افزونه جانبی
         */
        public static function has_token($plugin_slug) {
            return !empty(self::get_token($plugin_slug));
        }

        /**
         * wrapper برای گرفتن config فعلی
         */
        public static function get_config($plugin_slug) {
            return self::$configs[$plugin_slug] ?? [];
        }

        protected static function normalize_config($config) {
            $config = is_array($config) ? $config : [];

            return [
                'slug'             => sanitize_key($config['slug'] ?? ''),
                'name'             => sanitize_text_field($config['name'] ?? ''),
                'integration_code' => self::normalize_integration_code($config['integration_code'] ?? ''),
                'namespace'        => trim(sanitize_text_field($config['namespace'] ?? ''), '/'),
            ];
        }

        protected static function normalize_integration_code($integration_code) {
            $integration_code = strtoupper(trim((string) $integration_code));
            $integration_code = preg_replace('/[^A-Z0-9\-_]/', '-', $integration_code);
            return trim((string) $integration_code, '-_');
        }
    }
}
