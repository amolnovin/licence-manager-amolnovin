<?php
namespace WPLM\Admin;

use WPLM\Database;
use WPLM\Options;
use WPLM\Repositories\CategoryRepository;
use WPLM\Repositories\DiscountRepository;
use WPLM\Repositories\LicenseRepository;
use WPLM\Repositories\ProductRepository;
use WPLM\Repositories\VersionRepository;
use WPLM\Security;
use WPLM\Services\LicenseNotificationService;
use WPLM\Services\PackageService;
use WPLM\Services\SmsService;

defined('ABSPATH') || exit;

final class Actions
{
    public static function save_product(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_save_product');
        $data = self::product_payload($_POST);
        (new ProductRepository())->save($data);
        Security::redirect('wplm-products', ['saved' => 1]);
    }

    public static function ajax_save_product(): void
    {
        if (!Security::can()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز است.'], 403);
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'wplm_admin_ajax')) {
            wp_send_json_error(['message' => 'بررسی امنیتی ناموفق بود.'], 403);
        }

        $data = self::product_payload($_POST);
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'نام محصول الزامی است.'], 422);
        }
        $id = (new ProductRepository())->save($data);
        wp_send_json_success(['id' => $id, 'message' => 'محصول ذخیره شد.']);
    }

    private static function product_payload(array $source): array
    {
        $data = [
            'id' => isset($source['id']) ? absint($source['id']) : 0,
            'category_id' => isset($source['category_id']) ? absint($source['category_id']) : 0,
            'name' => isset($source['name']) ? sanitize_text_field(wp_unslash($source['name'])) : '',
            'slug' => isset($source['slug']) ? sanitize_title(wp_unslash($source['slug'])) : '',
            'type' => isset($source['type']) && in_array($source['type'], ['plugin', 'theme'], true) ? sanitize_key($source['type']) : 'plugin',
            'cover_url' => isset($source['cover_url']) ? esc_url_raw(wp_unslash($source['cover_url'])) : '',
            'short_description' => isset($source['short_description']) ? sanitize_textarea_field(wp_unslash($source['short_description'])) : '',
            'description' => isset($source['description']) ? wp_kses_post(wp_unslash($source['description'])) : '',
            'version' => isset($source['version']) ? sanitize_text_field(wp_unslash($source['version'])) : '1.0.0',
            'price' => isset($source['price']) ? (float) $source['price'] : 0,
            'currency' => isset($source['currency']) ? sanitize_text_field(wp_unslash($source['currency'])) : 'IRT',
            'status' => isset($source['status']) && in_array($source['status'], ['active', 'draft'], true) ? sanitize_key($source['status']) : 'draft',
            'license_mode' => isset($source['license_mode']) && 'none' === $source['license_mode'] ? 'none' : 'required',
            'is_visible' => isset($source['is_visible']) ? 1 : 0,
        ];
        $state = isset($source['product_state']) ? sanitize_key(wp_unslash($source['product_state'])) : '';
        if ('draft' === $state) {
            $data['status'] = 'draft';
            $data['license_mode'] = 'required';
        } elseif ('active_none' === $state) {
            $data['status'] = 'active';
            $data['license_mode'] = 'none';
        } elseif ('active_required' === $state) {
            $data['status'] = 'active';
            $data['license_mode'] = 'required';
        }
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        return $data;
    }

    public static function delete_product(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_delete_product');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id) {
            (new ProductRepository())->delete($id);
        }
        Security::redirect('wplm-products', ['deleted' => 1]);
    }

    public static function save_category(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_save_category');
        $data = [
            'id' => isset($_POST['id']) ? absint($_POST['id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'slug' => isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '',
            'type' => isset($_POST['type']) && in_array($_POST['type'], ['all', 'plugin', 'theme'], true) ? sanitize_key($_POST['type']) : 'all',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'status' => isset($_POST['status']) && in_array($_POST['status'], ['active', 'draft'], true) ? sanitize_key($_POST['status']) : 'active',
        ];
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        (new CategoryRepository())->save($data);
        Security::redirect('wplm-categories', ['saved' => 1]);
    }

    public static function delete_category(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_delete_category');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id) {
            (new CategoryRepository())->delete($id);
        }
        Security::redirect('wplm-categories', ['deleted' => 1]);
    }

    public static function save_discount(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_save_discount');

        $data = [
            'id' => isset($_POST['id']) ? absint($_POST['id']) : 0,
            'code' => isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '',
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'discount_type' => isset($_POST['discount_type']) && 'fixed' === $_POST['discount_type'] ? 'fixed' : 'percent',
            'amount' => isset($_POST['amount']) ? (float) $_POST['amount'] : 0,
            'product_id' => isset($_POST['product_id']) ? absint($_POST['product_id']) : 0,
            'category_id' => isset($_POST['category_id']) ? absint($_POST['category_id']) : 0,
            'starts_at' => isset($_POST['starts_at']) && $_POST['starts_at'] !== '' ? sanitize_text_field(wp_unslash($_POST['starts_at'])) . ' 00:00:00' : null,
            'ends_at' => isset($_POST['ends_at']) && $_POST['ends_at'] !== '' ? sanitize_text_field(wp_unslash($_POST['ends_at'])) . ' 23:59:59' : null,
            'usage_limit' => isset($_POST['usage_limit']) ? absint($_POST['usage_limit']) : 0,
            'status' => isset($_POST['status']) && 'draft' === $_POST['status'] ? 'draft' : 'active',
        ];

        if (!$data['code'] || $data['amount'] <= 0) {
            Security::redirect('wplm-discounts', ['wplm_error' => rawurlencode('کد تخفیف و مقدار تخفیف الزامی است.')]);
        }

        (new DiscountRepository())->save($data);
        Security::redirect('wplm-discounts', ['saved' => 1]);
    }

    public static function delete_discount(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_delete_discount');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id) {
            (new DiscountRepository())->delete($id);
        }
        Security::redirect('wplm-discounts', ['deleted' => 1]);
    }

    public static function save_settings(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_save_settings');

        $settings = [
            'license_expire_days' => isset($_POST['license_expire_days']) ? max(1, absint($_POST['license_expire_days'])) : 365,
            'allowed_domains' => isset($_POST['allowed_domains']) ? max(1, absint($_POST['allowed_domains'])) : 1,
            'currency' => isset($_POST['currency']) ? sanitize_text_field(wp_unslash($_POST['currency'])) : 'IRT',
            'price_unit' => isset($_POST['price_unit']) && 'rial' === $_POST['price_unit'] ? 'rial' : 'toman',
            'notify_admin_email' => isset($_POST['notify_admin_email']) ? sanitize_email(wp_unslash($_POST['notify_admin_email'])) : get_option('admin_email'),
            'default_gateway' => isset($_POST['default_gateway']) && 'zarinpal' === $_POST['default_gateway'] ? 'zarinpal' : 'zibal',
            'zibal_merchant' => isset($_POST['zibal_merchant']) ? sanitize_text_field(wp_unslash($_POST['zibal_merchant'])) : '',
            'zibal_test_mode' => isset($_POST['zibal_test_mode']) ? '1' : '0',
            'zarinpal_merchant' => isset($_POST['zarinpal_merchant']) ? sanitize_text_field(wp_unslash($_POST['zarinpal_merchant'])) : '',
            'zarinpal_sandbox' => isset($_POST['zarinpal_sandbox']) ? '1' : '0',
            'store_page_id' => isset($_POST['store_page_id']) ? absint($_POST['store_page_id']) : 0,
            'account_page_id' => isset($_POST['account_page_id']) ? absint($_POST['account_page_id']) : 0,
            'payment_result_page_id' => isset($_POST['payment_result_page_id']) ? absint($_POST['payment_result_page_id']) : 0,
            'sms_enabled' => isset($_POST['sms_enabled']) ? '1' : '0',
            'sms_provider' => isset($_POST['sms_provider']) ? sanitize_key(wp_unslash($_POST['sms_provider'])) : 'ippanel_core',
            'sms_api_key' => isset($_POST['sms_api_key']) ? sanitize_text_field(wp_unslash($_POST['sms_api_key'])) : '',
            'sms_sender' => isset($_POST['sms_sender']) ? sanitize_text_field(wp_unslash($_POST['sms_sender'])) : '',
            'sms_license_created_mode' => isset($_POST['sms_license_created_mode']) && 'pattern' === $_POST['sms_license_created_mode'] ? 'pattern' : 'text',
            'sms_license_created_pattern' => isset($_POST['sms_license_created_pattern']) ? sanitize_text_field(wp_unslash($_POST['sms_license_created_pattern'])) : '',
            'sms_license_created_template' => isset($_POST['sms_license_created_template']) ? sanitize_textarea_field(wp_unslash($_POST['sms_license_created_template'])) : '',
        ];

        Options::update($settings);
        Security::redirect('wplm-settings', ['saved' => 1]);
    }

    public static function save_license(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_save_license');

        $repo = new LicenseRepository();
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $data = [
            'product_id' => isset($_POST['product_id']) ? absint($_POST['product_id']) : 0,
            'license_key' => isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '',
            'customer_email' => isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '',
            'customer_mobile' => isset($_POST['customer_mobile']) ? sanitize_text_field(wp_unslash($_POST['customer_mobile'])) : '',
            'allowed_domains' => isset($_POST['allowed_domains']) ? max(1, absint($_POST['allowed_domains'])) : 1,
            'domains' => isset($_POST['domains']) ? sanitize_textarea_field(wp_unslash($_POST['domains'])) : '',
            'status' => isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'active',
            'expires_at' => isset($_POST['expires_at']) && $_POST['expires_at'] !== '' ? sanitize_text_field(wp_unslash($_POST['expires_at'])) . ' 23:59:59' : null,
        ];

        if (empty($data['product_id'])) {
            Security::redirect('wplm-licenses', ['wplm_error' => rawurlencode('انتخاب محصول الزامی است.')]);
        }

        $license = $id ? $repo->save_license($id, $data) : $repo->create_manual($data);
        Security::redirect('wplm-licenses', ['saved' => 1, 'edit' => $license ? (int) $license->id : 0]);
    }

    public static function set_license_status(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_license_action');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'inactive';
        if ($id) {
            (new LicenseRepository())->set_status($id, $status);
        }
        Security::redirect('wplm-licenses', ['saved' => 1]);
    }

    public static function extend_license(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_license_action');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $days = isset($_GET['days']) ? absint($_GET['days']) : 365;
        if ($id) {
            (new LicenseRepository())->extend($id, max(1, $days));
        }
        Security::redirect('wplm-licenses', ['saved' => 1, 'edit' => $id]);
    }

    public static function remove_license_domain(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_license_action');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $domain = isset($_GET['domain']) ? sanitize_text_field(wp_unslash($_GET['domain'])) : '';
        if ($id && $domain) {
            (new LicenseRepository())->remove_domain($id, $domain);
        }
        Security::redirect('wplm-licenses', ['saved' => 1, 'edit' => $id]);
    }

    public static function resend_license(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_license_action');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $license = $id ? (new LicenseRepository())->find($id) : null;
        if ($license) {
            LicenseNotificationService::resend($license);
        }
        Security::redirect('wplm-licenses', ['resent' => 1, 'edit' => $id]);
    }

    public static function save_version(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_save_version');

        if (!current_user_can('upload_files')) {
            Security::redirect('wplm-updates', ['wplm_error' => rawurlencode('برای آپلود فایل نیاز به دسترسی upload_files دارید.')]);
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product = (new ProductRepository())->find($product_id);
        if (!$product) {
            Security::redirect('wplm-updates', ['wplm_error' => rawurlencode('محصول انتخاب‌شده معتبر نیست.')]);
        }

        $version = isset($_POST['version']) ? sanitize_text_field(wp_unslash($_POST['version'])) : '';
        if (!$version) {
            Security::redirect('wplm-updates', ['wplm_error' => rawurlencode('شماره نسخه الزامی است.')]);
        }

        $upload = ['success' => false, 'message' => 'فایل ZIP الزامی است.'];
        if (!empty($_FILES['package_zip']) && is_array($_FILES['package_zip'])) {
            $upload = PackageService::store_zip_upload($_FILES['package_zip'], $product->slug, $version);
        }

        if (empty($upload['success'])) {
            Security::redirect('wplm-updates', ['wplm_error' => rawurlencode($upload['message'])]);
        }

        $repo = new VersionRepository();
        $id = $repo->save([
            'product_id' => $product_id,
            'version' => $version,
            'package_url' => '',
            'file_path' => $upload['file_path'],
            'file_name' => $upload['file_name'],
            'file_size' => $upload['file_size'],
            'changelog' => isset($_POST['changelog']) ? wp_kses_post(wp_unslash($_POST['changelog'])) : '',
            'requires_wp' => isset($_POST['requires_wp']) ? sanitize_text_field(wp_unslash($_POST['requires_wp'])) : '',
            'tested_wp' => isset($_POST['tested_wp']) ? sanitize_text_field(wp_unslash($_POST['tested_wp'])) : '',
            'requires_php' => isset($_POST['requires_php']) ? sanitize_text_field(wp_unslash($_POST['requires_php'])) : '',
            'is_current' => isset($_POST['is_current']) ? 1 : 0,
        ]);

        if (isset($_POST['is_current'])) {
            $repo->make_current($id);
        }

        Security::redirect('wplm-updates', ['saved' => 1, 'product_id' => $product_id]);
    }

    public static function make_current_version(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_version_action');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $version = $id ? (new VersionRepository())->find($id) : null;
        if ($id) {
            (new VersionRepository())->make_current($id);
        }
        Security::redirect('wplm-updates', ['saved' => 1, 'product_id' => $version ? (int) $version->product_id : 0]);
    }

    public static function delete_version(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_version_action');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $repo = new VersionRepository();
        $version = $id ? $repo->find($id) : null;
        if ($version) {
            $base = realpath(PackageService::secure_dir());
            $file = realpath(trailingslashit(PackageService::secure_dir()) . ltrim((string) $version->file_path, '/'));
            if ($base && $file && 0 === strpos($file, $base) && is_file($file)) {
                @unlink($file);
            }
            $repo->delete($id);
        }
        Security::redirect('wplm-updates', ['deleted' => 1, 'product_id' => $version ? (int) $version->product_id : 0]);
    }

    public static function approve_request(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_request_action');
        $request_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($request_id) {
            $license = (new LicenseRepository())->approve_request($request_id);
            if ($license && !empty($license->customer_mobile)) {
                SmsService::notify_license_created($license->customer_mobile, $license->license_key, ['license' => $license]);
            }
        }
        Security::redirect('wplm-requests', ['approved' => 1]);
    }

    public static function reject_request(): void
    {
        Security::require_cap();
        Security::verify_nonce('wplm_request_action');
        global $wpdb;
        $request_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($request_id) {
            $wpdb->update(Database::table('license_requests'), ['status' => 'rejected', 'updated_at' => Database::now()], ['id' => $request_id], ['%s', '%s'], ['%d']);
        }
        Security::redirect('wplm-requests', ['rejected' => 1]);
    }
}
