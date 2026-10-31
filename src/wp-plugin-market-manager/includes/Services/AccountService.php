<?php
namespace WPLM\Services;

use WPLM\Options;

defined('ABSPATH') || exit;

final class AccountService
{
    public static function update_password(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(Options::page_url('account_page_id')));
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wplm_account_update_password')) {
            wp_die('درخواست معتبر نیست.', '', ['response' => 403]);
        }

        $current = isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '';
        $new = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
        $confirm = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';
        $user = wp_get_current_user();
        $url = add_query_arg('wplm_tab', 'settings', Options::page_url('account_page_id'));

        if (!$user || !$user->ID || !wp_check_password($current, $user->user_pass, $user->ID)) {
            wp_safe_redirect(add_query_arg('wplm_error', rawurlencode('رمز عبور فعلی درست نیست.'), $url));
            exit;
        }

        if (strlen($new) < 8) {
            wp_safe_redirect(add_query_arg('wplm_error', rawurlencode('رمز عبور جدید باید حداقل ۸ کاراکتر باشد.'), $url));
            exit;
        }

        if ($new !== $confirm) {
            wp_safe_redirect(add_query_arg('wplm_error', rawurlencode('تکرار رمز عبور جدید مطابقت ندارد.'), $url));
            exit;
        }

        wp_set_password($new, $user->ID);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        wp_safe_redirect(add_query_arg('wplm_saved', '1', $url));
        exit;
    }
}
