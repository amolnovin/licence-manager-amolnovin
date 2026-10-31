<?php
namespace WPLM;

defined('ABSPATH') || exit;

final class Security
{
    /**
     * capability اصلی افزونه (یک meta capability است).
     */
    public const PRIMARY_CAP = 'manage_wplm';

    /**
     * capability نقش «مدیر پروژه» که باید دسترسی کامل افزونه را داشته باشد.
     */
    public const CONTRACTS_CAP = 'ani_contracts_manager';

    /**
     * آیا کاربر فعلی دسترسی مدیریت افزونه دارد؟
     * (مدیر یا مدیر پروژه contracts)
     */
    public static function can(): bool
    {
        return current_user_can(self::PRIMARY_CAP);
    }

    public static function require_cap(): void
    {
        if (!self::can()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'wp-plugin-market-manager'), 403);
        }
    }

    /**
     * map_meta_cap: اجازه‌ی capability 'manage_wplm' را به هر کاربری می‌دهد
     * که manage_options (مدیر) یا ani_contracts_manager (مدیر پروژه) دارد.
     *
     * این کار باعث می‌شود همه‌ی چک‌های دسترسی (نمایش منو، require_cap،
     * اکشن‌های admin-post و AJAX) به‌صورت خودکار برای نقش contracts هم
     * عبور کنند، بدون نیاز به تغییر capability هر منو/اکشن.
     */
    public static function map_meta_cap(array $caps, string $cap, int $user_id, array $args = []): array
    {
        if (self::PRIMARY_CAP !== $cap) {
            return $caps;
        }

        $user = get_userdata($user_id);
        if ($user && ($user->has_cap('manage_options') || $user->has_cap(self::CONTRACTS_CAP))) {
            return ['exist'];
        }

        return ['do_not_allow'];
    }

    public static function verify_nonce(string $action, string $field = '_wpnonce'): void
    {
        $nonce = isset($_REQUEST[$field]) ? sanitize_text_field(wp_unslash($_REQUEST[$field])) : '';
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('Security check failed.', 'wp-plugin-market-manager'), 403);
        }
    }

    public static function redirect(string $page, array $args = []): void
    {
        $url = add_query_arg(array_merge(['page' => $page], $args), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public static function sanitize_domain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        return sanitize_text_field($domain);
    }

    /**
     * پاکاسازی شناسه ماشین (machine_id) برای برنامه‌های ویندوز:
     * فقط حروف a-f و اعداد 0-9، حداکثر ۶۴ کاراکتر، حروف کوچک.
     * نمونه: 4d75bfe14a964dcfb15b6741b2ef6bc2
     */
    public static function sanitize_machine_id(string $machine_id): string
    {
        $machine_id = strtolower(trim($machine_id));
        $machine_id = preg_replace('/[^a-f0-9]/i', '', $machine_id);
        return substr($machine_id, 0, 64);
    }

    /**
     * آیا مقدار داده‌شده شبیه یک machine_id معتبر است؟
     * شکلมาตรฐาน: ۳۲ کاراکتر هگز (مثل fingerprint کارتگرافی).
     */
    public static function looks_like_machine_id(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', trim($value));
    }


    public static function client_ip(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $value = sanitize_text_field(wp_unslash($_SERVER[$key]));
                $parts = explode(',', $value);
                return trim($parts[0]);
            }
        }
        return '0.0.0.0';
    }

    public static function rate_limit(string $scope, int $limit = 30, int $window = 300): bool
    {
        $key = 'wplm_rl_' . md5($scope . '|' . self::client_ip());
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            return false;
        }
        set_transient($key, $count + 1, $window);
        return true;
    }

    public static function generate_license_key(): string
    {
        $raw = strtoupper(wp_generate_password(24, false, false));
        return trim(chunk_split($raw, 4, '-'), '-');
    }

    public static function mask_key(string $key): string
    {
        if (strlen($key) <= 8) {
            return '••••';
        }
        return substr($key, 0, 4) . '-••••-••••-' . substr($key, -4);
    }
}
