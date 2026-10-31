<?php
namespace WPLM\Services;

use WPLM\Options;
use WPLM\Repositories\ProductRepository;

defined('ABSPATH') || exit;

final class SmsService
{
    public const ADDON_SLUG = 'wp-plugin-market-manager';
    public const ADDON_NAME = 'سامانه فروش و لایسنس افزونه و قالب';
    public const INTEGRATION_CODE = 'WPLM-LICENSE-HUB';
    public const REST_NAMESPACE = 'wplm-ippanel/v1';

    public static function boot(): void
    {
        $helper = WPLM_PATH . 'includes/Vendor/ippanel-core-addon-helper.php';
        if (is_readable($helper)) {
            require_once $helper;
        }

        if (class_exists('IPPanel_Core_Addon_Client')) {
            \IPPanel_Core_Addon_Client::boot([
                'slug' => self::ADDON_SLUG,
                'name' => self::ADDON_NAME,
                'integration_code' => self::INTEGRATION_CODE,
                'namespace' => self::REST_NAMESPACE,
            ]);
        }
    }

    public static function has_ippanel_core(): bool
    {
        return function_exists('ippanel_register_external_plugin') || function_exists('ippanel_send_external_sms');
    }

    public static function has_ippanel_token(): bool
    {
        return class_exists('IPPanel_Core_Addon_Client') && \IPPanel_Core_Addon_Client::has_token(self::ADDON_SLUG);
    }

    public static function get_ippanel_callback_url(): string
    {
        return home_url('/wp-json/' . self::REST_NAMESPACE . '/receive-key');
    }

    public static function notify_license_created(string $mobile, string $license_key, array $context = []): bool
    {
        $settings = Options::get();
        if ('1' !== $settings['sms_enabled']) {
            return false;
        }

        $license = $context['license'] ?? null;
        $product = null;
        if ($license && !empty($license->product_id)) {
            $product = (new ProductRepository())->find((int) $license->product_id);
        }

        $tokens = [
            '{license_key}' => $license_key,
            '{product}' => $product ? $product->name : 'محصول',
            '{domain}' => $license && !empty($license->domains) ? implode(',', (array) json_decode((string) $license->domains, true)) : '',
            '{expires}' => $license && !empty($license->expires_at) ? mysql2date('Y/m/d', $license->expires_at) : '',
            '{email}' => $license && !empty($license->customer_email) ? $license->customer_email : '',
        ];

        if ('ippanel_core' === $settings['sms_provider']) {
            return self::send_via_ippanel_core($mobile, $settings, $tokens);
        }

        $message = strtr((string) $settings['sms_license_created_template'], $tokens);
        return self::send($mobile, $message);
    }

    private static function send_via_ippanel_core(string $mobile, array $settings, array $tokens): bool
    {
        if (!class_exists('IPPanel_Core_Addon_Client')) {
            return false;
        }

        if ('pattern' === $settings['sms_license_created_mode'] && !empty($settings['sms_license_created_pattern'])) {
            $params = [
                'license_key' => $tokens['{license_key}'],
                'product' => $tokens['{product}'],
                'domain' => $tokens['{domain}'],
                'expires' => $tokens['{expires}'],
                'email' => $tokens['{email}'],
            ];
            $result = \IPPanel_Core_Addon_Client::send_pattern_sms(self::ADDON_SLUG, $mobile, $settings['sms_license_created_pattern'], $params);
        } else {
            $message = strtr((string) $settings['sms_license_created_template'], $tokens);
            $result = \IPPanel_Core_Addon_Client::send_text_sms(self::ADDON_SLUG, $mobile, $message);
        }

        return !is_wp_error($result);
    }

    public static function send(string $mobile, string $message): bool
    {
        $settings = Options::get();
        $provider = $settings['sms_provider'];

        /**
         * توسعه‌دهنده می‌تواند ارسال پیامک برای Providerهای غیر از ippanel-core را از این فیلتر اضافه کند.
         */
        $handled = apply_filters('wplm_sms_send', null, $provider, $mobile, $message, $settings);
        if (is_bool($handled)) {
            return $handled;
        }

        return false;
    }
}
