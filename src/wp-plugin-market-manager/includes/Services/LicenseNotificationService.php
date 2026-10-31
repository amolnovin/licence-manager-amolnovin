<?php
namespace WPLM\Services;

use WPLM\Options;
use WPLM\Repositories\ProductRepository;

defined('ABSPATH') || exit;

final class LicenseNotificationService
{
    public static function resend($license): bool
    {
        if (!$license) {
            return false;
        }

        $email_sent = self::send_email($license);
        $sms_sent = false;
        if (!empty($license->customer_mobile)) {
            $sms_sent = SmsService::notify_license_created($license->customer_mobile, $license->license_key, ['license' => $license]);
        }

        return $email_sent || $sms_sent;
    }

    public static function send_email($license): bool
    {
        if (!$license || empty($license->customer_email) || !is_email($license->customer_email)) {
            return false;
        }

        $product = (new ProductRepository())->find((int) $license->product_id);
        $settings = Options::get();
        $subject = sprintf('لایسنس محصول %s', $product ? $product->name : 'خریداری‌شده');
        $domains = json_decode((string) $license->domains, true);
        $domains = is_array($domains) && $domains ? implode(', ', $domains) : 'ثبت نشده';
        $account_url = Options::page_url('account_page_id');

        $message = sprintf(
            "سلام،\n\nاطلاعات لایسنس شما به شرح زیر است:\n\nمحصول: %s\nکلید لایسنس: %s\nوضعیت: %s\nدامنه‌ها: %s\nتعداد دامنه مجاز: %d\nتاریخ انقضا: %s\n\nحساب کاربری: %s\n\nبا احترام",
            $product ? $product->name : 'محصول',
            $license->license_key,
            $license->status,
            $domains,
            (int) $license->allowed_domains,
            !empty($license->expires_at) ? mysql2date('Y/m/d', $license->expires_at) : 'نامحدود',
            $account_url
        );

        $headers = [];
        if (!empty($settings['notify_admin_email']) && is_email($settings['notify_admin_email'])) {
            $headers[] = 'Reply-To: ' . $settings['notify_admin_email'];
        }

        return (bool) wp_mail($license->customer_email, $subject, $message, $headers);
    }
}
