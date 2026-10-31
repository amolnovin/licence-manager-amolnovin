<?php
namespace WPLM\Services;

use WPLM\Repositories\LicenseRepository;
use WPLM\Repositories\OrderRepository;
use WPLM\Repositories\ProductRepository;
use WPLM\Repositories\VersionRepository;
use WPLM\Security;

defined('ABSPATH') || exit;

final class PackageService
{
    public static function secure_dir(): string
    {
        $uploads = wp_upload_dir(null, false);
        return trailingslashit($uploads['basedir']) . 'wplm-secure-packages';
    }

    public static function ensure_secure_dir(): bool
    {
        $dir = self::secure_dir();
        if (!wp_mkdir_p($dir)) {
            return false;
        }

        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \".*\">\n    Require all denied\n</FilesMatch>\n");
        }

        return true;
    }

    public static function store_zip_upload(array $file, string $product_slug, string $version): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'فایل ZIP ارسال نشده است.'];
        }

        if (!self::ensure_secure_dir()) {
            return ['success' => false, 'message' => 'پوشه امن بسته‌ها قابل ایجاد نیست.'];
        }

        $original_name = sanitize_file_name((string) $file['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if ('zip' !== $ext) {
            return ['success' => false, 'message' => 'فقط فایل ZIP مجاز است.'];
        }

        $check = wp_check_filetype($original_name, ['zip' => 'application/zip']);
        if (empty($check['ext']) || 'zip' !== $check['ext']) {
            return ['success' => false, 'message' => 'نوع فایل معتبر نیست.'];
        }

        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $opened = $zip->open($file['tmp_name']);
            if (true !== $opened) {
                return ['success' => false, 'message' => 'ساختار فایل ZIP معتبر نیست.'];
            }
            $zip->close();
        }

        $subdir = sanitize_key($product_slug) . '/' . sanitize_file_name($version);
        $target_dir = trailingslashit(self::secure_dir()) . $subdir;
        if (!wp_mkdir_p($target_dir)) {
            return ['success' => false, 'message' => 'پوشه نسخه قابل ایجاد نیست.'];
        }

        $filename = wp_unique_filename($target_dir, $original_name);
        $target = trailingslashit($target_dir) . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => false, 'message' => 'انتقال فایل آپلودشده ناموفق بود.'];
        }

        @chmod($target, 0644);

        return [
            'success' => true,
            'file_path' => $subdir . '/' . $filename,
            'file_name' => $filename,
            'file_size' => filesize($target),
        ];
    }

    public static function download_url(int $version_id, string $license_key, string $domain): string
    {
        $token = self::generate_token([
            'version_id' => $version_id,
            'license_key' => $license_key,
            'domain' => Security::sanitize_domain($domain),
            'exp' => time() + HOUR_IN_SECONDS,
        ]);

        return add_query_arg([
            'action' => 'wplm_download_package',
            'token' => $token,
        ], admin_url('admin-post.php'));
    }

    public static function account_download_url(int $version_id, int $license_id, int $user_id, int $order_id = 0): string
    {
        $token = self::generate_token(array_filter([
            'version_id' => $version_id,
            'license_id' => $license_id ?: null,
            'order_id' => $order_id ?: null,
            'user_id' => $user_id,
            'scope' => 'account',
            'exp' => time() + HOUR_IN_SECONDS,
        ]));

        return add_query_arg([
            'action' => 'wplm_download_package',
            'token' => $token,
        ], admin_url('admin-post.php'));
    }

    public static function generate_token(array $payload): string
    {
        $json = wp_json_encode($payload);
        $body = self::base64url_encode($json);
        $sig = hash_hmac('sha256', $body, wp_salt('auth'));
        return $body . '.' . $sig;
    }

    public static function parse_token(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (2 !== count($parts)) {
            return ['valid' => false, 'code' => 'invalid_token'];
        }

        [$body, $sig] = $parts;
        $expected = hash_hmac('sha256', $body, wp_salt('auth'));
        if (!hash_equals($expected, $sig)) {
            return ['valid' => false, 'code' => 'bad_signature'];
        }

        $payload = json_decode(self::base64url_decode($body), true);
        if (!is_array($payload)) {
            return ['valid' => false, 'code' => 'bad_payload'];
        }

        if (empty($payload['exp']) || (int) $payload['exp'] < time()) {
            return ['valid' => false, 'code' => 'expired_token'];
        }

        return ['valid' => true, 'payload' => $payload];
    }

    public static function handle_download(): void
    {
        if (!Security::rate_limit('package_download', 80, 300)) {
            wp_die('درخواست دانلود بیش از حد مجاز است.', '', ['response' => 429]);
        }

        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $parsed = self::parse_token($token);
        if (empty($parsed['valid'])) {
            wp_die('لینک دانلود معتبر نیست یا منقضی شده است.', '', ['response' => 403]);
        }

        $payload = $parsed['payload'];
        $version = (new VersionRepository())->find(absint($payload['version_id'] ?? 0));
        if (!$version || empty($version->file_path)) {
            wp_die('فایل نسخه پیدا نشد.', '', ['response' => 404]);
        }

        $license_repo = new LicenseRepository();
        $product = (new ProductRepository())->find((int) $version->product_id);
        $license_required = !$product || 'none' !== ($product->license_mode ?? 'required');
        if (!empty($payload['license_id'])) {
            $license = $license_repo->find(absint($payload['license_id']));
            if (!$license || (int) $license->product_id !== (int) $version->product_id || 'active' !== $license->status) {
                wp_die('لایسنس برای دانلود این بسته معتبر نیست.', '', ['response' => 403]);
            }
            if (!empty($license->expires_at) && strtotime($license->expires_at) < time()) {
                wp_die('لایسنس منقضی شده است.', '', ['response' => 403]);
            }
            if (!empty($payload['user_id'])) {
                $user_id = absint($payload['user_id']);
                if (!is_user_logged_in() || get_current_user_id() !== $user_id || !(new OrderRepository())->user_owns_license($user_id, (int) $license->id)) {
                    wp_die('شما مجوز دانلود این فایل را ندارید.', '', ['response' => 403]);
                }
            }
        } elseif (!empty($payload['order_id']) && !empty($payload['user_id']) && !$license_required) {
            $user_id = absint($payload['user_id']);
            $order_id = absint($payload['order_id']);
            if (!is_user_logged_in() || get_current_user_id() !== $user_id || !(new OrderRepository())->user_owns_order($user_id, $order_id, (int) $version->product_id)) {
                wp_die('شما مجوز دانلود این فایل را ندارید.', '', ['response' => 403]);
            }
        } elseif (!$license_required) {
            // Public update/download token for products that do not require license.
        } else {
            $license_key = sanitize_text_field((string) ($payload['license_key'] ?? ''));
            $domain = Security::sanitize_domain((string) ($payload['domain'] ?? ''));
            $valid = $license_repo->validate($license_key, (int) $version->product_id, $domain);
            if (empty($valid['valid'])) {
                wp_die('لایسنس برای دانلود این بسته معتبر نیست.', '', ['response' => 403]);
            }
        }

        $base = realpath(self::secure_dir());
        $file = realpath(trailingslashit(self::secure_dir()) . ltrim((string) $version->file_path, '/'));
        if (!$base || !$file || 0 !== strpos($file, $base) || !is_readable($file)) {
            wp_die('فایل بسته قابل خواندن نیست.', '', ['response' => 404]);
        }

        $filename = $version->file_name ?: (($product ? $product->slug : 'package') . '-' . $version->version . '.zip');
        (new VersionRepository())->increment_download_count((int) $version->id);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        while (ob_get_level()) {
            @ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    private static function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
