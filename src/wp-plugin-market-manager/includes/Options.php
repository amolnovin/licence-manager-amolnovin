<?php
namespace WPLM;

defined('ABSPATH') || exit;

final class Options
{
    public const KEY = 'wplm_settings';

    public static function defaults(): array
    {
        return [
            'license_expire_days' => 365,
            'allowed_domains' => 1,
            'currency' => 'IRT',
            'price_unit' => 'toman',
            'sms_enabled' => '0',
            'sms_provider' => 'ippanel_core',
            'sms_api_key' => '',
            'sms_sender' => '',
            'sms_license_created_mode' => 'text',
            'sms_license_created_pattern' => '',
            'sms_license_created_template' => 'لایسنس {product} برای دامنه {domain} ایجاد شد: {license_key}',
            'notify_admin_email' => get_option('admin_email'),
            'default_gateway' => 'zibal',
            'zibal_merchant' => '',
            'zibal_test_mode' => '1',
            'zarinpal_merchant' => '',
            'zarinpal_sandbox' => '1',
            'store_page_id' => 0,
            'account_page_id' => 0,
            'payment_result_page_id' => 0,
        ];
    }

    public static function get(): array
    {
        $saved = get_option(self::KEY, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    public static function update(array $data): void
    {
        update_option(self::KEY, wp_parse_args($data, self::defaults()), false);
    }

    public static function ensure_defaults(): void
    {
        if (false === get_option(self::KEY, false)) {
            add_option(self::KEY, self::defaults(), '', false);
        }
    }

    public static function page_url(string $key): string
    {
        $settings = self::get();
        $page_id = !empty($settings[$key]) ? absint($settings[$key]) : 0;
        return $page_id ? (string) get_permalink($page_id) : home_url('/');
    }
}
