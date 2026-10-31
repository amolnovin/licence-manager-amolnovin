<?php
namespace WPLM\Admin;

use WPLM\Security;

defined('ABSPATH') || exit;

final class Menu
{
    public function register(): void
    {
        add_menu_page(
            __('سامانه فروش و لایسنس', 'wp-plugin-market-manager'),
            __('فروش و لایسنس', 'wp-plugin-market-manager'),
            'manage_wplm',
            'wplm-dashboard',
            [$this, 'dashboard'],
            'dashicons-admin-network',
            56
        );

        add_submenu_page('wplm-dashboard', __('داشبورد', 'wp-plugin-market-manager'), __('داشبورد', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-dashboard', [$this, 'dashboard']);
        add_submenu_page('wplm-dashboard', __('محصولات', 'wp-plugin-market-manager'), __('محصولات', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-products', [$this, 'products']);
        add_submenu_page('wplm-dashboard', __('دسته‌بندی‌ها', 'wp-plugin-market-manager'), __('دسته‌بندی‌ها', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-categories', [$this, 'categories']);
        add_submenu_page('wplm-dashboard', __('سفارش‌ها', 'wp-plugin-market-manager'), __('سفارش‌ها', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-sales', [$this, 'sales']);
        add_submenu_page('wplm-dashboard', __('کدهای تخفیف', 'wp-plugin-market-manager'), __('کدهای تخفیف', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-discounts', [$this, 'discounts']);
        add_submenu_page('wplm-dashboard', __('درخواست‌های لایسنس', 'wp-plugin-market-manager'), __('درخواست‌ها', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-requests', [$this, 'requests']);
        add_submenu_page('wplm-dashboard', __('لایسنس‌ها', 'wp-plugin-market-manager'), __('لایسنس‌ها', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-licenses', [$this, 'licenses']);
        add_submenu_page('wplm-dashboard', __('بروزرسانی‌ها', 'wp-plugin-market-manager'), __('بروزرسانی‌ها', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-updates', [$this, 'updates']);
        add_submenu_page('wplm-dashboard', __('پیامک‌ها', 'wp-plugin-market-manager'), __('پیامک‌ها', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-sms', [$this, 'sms']);
        add_submenu_page('wplm-dashboard', __('تنظیمات', 'wp-plugin-market-manager'), __('تنظیمات', 'wp-plugin-market-manager'), 'manage_wplm', 'wplm-settings', [$this, 'settings']);
    }

    public function dashboard(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/dashboard.php'; }
    public function products(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/products.php'; }
    public function categories(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/categories.php'; }
    public function sales(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/sales.php'; }
    public function discounts(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/discounts.php'; }
    public function requests(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/requests.php'; }
    public function licenses(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/licenses.php'; }
    public function updates(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/updates.php'; }
    public function sms(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/sms.php'; }
    public function settings(): void { Security::require_cap(); require WPLM_PATH . 'admin/views/settings.php'; }
}
