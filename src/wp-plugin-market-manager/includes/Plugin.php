<?php
namespace WPLM;

use WPLM\Admin\Actions;
use WPLM\Admin\Menu;
use WPLM\REST\LicenseController;
use WPLM\REST\UpdateController;
use WPLM\Services\AccountService;
use WPLM\Services\PackageService;
use WPLM\Services\PurchaseService;
use WPLM\Services\SmsService;

defined('ABSPATH') || exit;

final class Plugin
{
    private static $instance = null;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function boot(): void
    {
        SmsService::boot();

        // دسترسی نقش «مدیر پروژه» (contracts / ani_contracts_manager) به افزونه
        add_filter('map_meta_cap', [Security::class, 'map_meta_cap'], 10, 4);

        add_action('init', [$this, 'register_post_independent_features']);
        add_action('admin_init', [Activator::class, 'maybe_upgrade'], 20);
        add_action('admin_init', [Activator::class, 'maybe_create_default_pages'], 30);
        add_action('admin_enqueue_scripts', [Assets::class, 'admin']);
        add_action('wp_enqueue_scripts', [Assets::class, 'public']);
        add_action('admin_menu', [new Menu(), 'register']);

        add_action('admin_post_wplm_save_product', [Actions::class, 'save_product']);
        add_action('wp_ajax_wplm_ajax_save_product', [Actions::class, 'ajax_save_product']);
        add_action('admin_post_wplm_delete_product', [Actions::class, 'delete_product']);
        add_action('admin_post_wplm_save_category', [Actions::class, 'save_category']);
        add_action('admin_post_wplm_delete_category', [Actions::class, 'delete_category']);
        add_action('admin_post_wplm_save_discount', [Actions::class, 'save_discount']);
        add_action('admin_post_wplm_delete_discount', [Actions::class, 'delete_discount']);
        add_action('admin_post_wplm_save_settings', [Actions::class, 'save_settings']);
        add_action('admin_post_wplm_save_license', [Actions::class, 'save_license']);
        add_action('admin_post_wplm_set_license_status', [Actions::class, 'set_license_status']);
        add_action('admin_post_wplm_extend_license', [Actions::class, 'extend_license']);
        add_action('admin_post_wplm_remove_license_domain', [Actions::class, 'remove_license_domain']);
        add_action('admin_post_wplm_resend_license', [Actions::class, 'resend_license']);
        add_action('admin_post_wplm_save_version', [Actions::class, 'save_version']);
        add_action('admin_post_wplm_make_current_version', [Actions::class, 'make_current_version']);
        add_action('admin_post_wplm_delete_version', [Actions::class, 'delete_version']);
        add_action('admin_post_wplm_approve_request', [Actions::class, 'approve_request']);
        add_action('admin_post_wplm_reject_request', [Actions::class, 'reject_request']);

        add_action('admin_post_wplm_add_to_cart', [PurchaseService::class, 'add_to_cart']);
        add_action('admin_post_nopriv_wplm_add_to_cart', [PurchaseService::class, 'add_to_cart']);
        add_action('admin_post_wplm_remove_from_cart', [PurchaseService::class, 'remove_from_cart']);
        add_action('admin_post_nopriv_wplm_remove_from_cart', [PurchaseService::class, 'remove_from_cart']);
        add_action('admin_post_wplm_account_update_password', [AccountService::class, 'update_password']);
        add_action('admin_post_wplm_start_purchase', [PurchaseService::class, 'start_purchase']);
        add_action('admin_post_nopriv_wplm_start_purchase', [PurchaseService::class, 'start_purchase']);
        add_action('admin_post_wplm_zibal_callback', [PurchaseService::class, 'zibal_callback']);
        add_action('admin_post_nopriv_wplm_zibal_callback', [PurchaseService::class, 'zibal_callback']);
        add_action('admin_post_wplm_zarinpal_callback', [PurchaseService::class, 'zarinpal_callback']);
        add_action('admin_post_nopriv_wplm_zarinpal_callback', [PurchaseService::class, 'zarinpal_callback']);
        add_action('admin_post_wplm_download_package', [PackageService::class, 'handle_download']);
        add_action('admin_post_nopriv_wplm_download_package', [PackageService::class, 'handle_download']);

        add_action('rest_api_init', [new LicenseController(), 'register_routes']);
        add_action('rest_api_init', [new UpdateController(), 'register_routes']);
    }

    public function register_post_independent_features(): void
    {
        add_shortcode('wplm_products', [Shortcodes::class, 'products']);
        add_shortcode('wplm_latest_carousel', [Shortcodes::class, 'latest_carousel']);
        add_shortcode('wplm_product_carousel', [Shortcodes::class, 'latest_carousel']);
        add_shortcode('wplm_store', [Shortcodes::class, 'store']);
        add_shortcode('wplm_account', [Shortcodes::class, 'account']);
        add_shortcode('wplm_payment_result', [Shortcodes::class, 'payment_result']);
    }
}
