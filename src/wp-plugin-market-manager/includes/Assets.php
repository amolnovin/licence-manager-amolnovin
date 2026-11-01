<?php
namespace WPLM;

defined('ABSPATH') || exit;

final class Assets
{
    public static function admin(string $hook): void
    {
        if (false === strpos($hook, 'wplm')) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_editor();
        wp_enqueue_style('wplm-admin', WPLM_URL . 'assets/admin.css', [], WPLM_VERSION);
        wp_enqueue_script('wplm-admin', WPLM_URL . 'assets/admin.js', ['jquery'], WPLM_VERSION, true);

        // فونت قالب فعال (اگر شناسایی شود) تا پیشخوان افزونه هم‌شکل بقیه‌ی سایت باشد
        $font_css = \WPLM\Support\ThemeFont::admin_css();
        if ('' !== $font_css) {
            wp_add_inline_style('wplm-admin', $font_css);
        }
        wp_localize_script('wplm-admin', 'WPLM_ADMIN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wplm_admin_ajax'),
            'saved' => 'محصول با موفقیت ذخیره شد.',
            'error' => 'خطا در ذخیره اطلاعات.',
        ]);
    }

    public static function public(): void
    {
        wp_register_style('wplm-public', WPLM_URL . 'assets/public.css', [], WPLM_VERSION);

        // فونت قالب فعال به‌صورت متغیر CSS روی ریشه‌ی فروشگاه
        $font_css = \WPLM\Support\ThemeFont::public_css();
        if ('' !== $font_css) {
            wp_add_inline_style('wplm-public', $font_css);
        }
    }
}
