<?php
use WPLM\Services\SmsService;

defined('ABSPATH') || exit;
$title = 'مرکز پیامک IPPanel';
$info_notice = 'بخش پیامک بر اساس فایل راهنمای IPPanel Core Addon Helper بازنویسی شد و بدون تداخل با افزونه‌های دیگر کار می‌کند.';
require WPLM_PATH . 'admin/views/header.php';
?>
<div class="wplm-panel">
    <h2>وضعیت اتصال</h2>
    <div class="wplm-grid wplm-stats wplm-sms-stats">
        <div class="wplm-card"><span>افزونه IPPanel Core</span><strong><?php echo SmsService::has_ippanel_core() ? 'فعال' : 'یافت نشد'; ?></strong></div>
        <div class="wplm-card"><span>توکن افزونه جانبی</span><strong><?php echo SmsService::has_ippanel_token() ? 'دریافت شده' : 'در انتظار'; ?></strong></div>
        <div class="wplm-card"><span>کد اتصال</span><strong dir="ltr"><?php echo esc_html(SmsService::INTEGRATION_CODE); ?></strong></div>
        <div class="wplm-card"><span>Slug افزونه</span><strong dir="ltr"><?php echo esc_html(SmsService::ADDON_SLUG); ?></strong></div>
    </div>
</div>

<div class="wplm-panel">
    <h2>روش اتصال به ippanel-core</h2>
    <ol class="wplm-roadmap">
        <li>افزونه <strong>ippanel-core</strong> را نصب و فعال کنید.</li>
        <li>این سامانه به صورت خودکار با اطلاعات زیر خود را در ippanel-core ثبت می‌کند.</li>
        <li>ippanel-core باید کلید دسترسی را به callback زیر ارسال کند.</li>
        <li>بعد از دریافت token، پیامک متنی یا پترن از طریق کلاس <code>IPPanel_Core_Addon_Client</code> ارسال می‌شود.</li>
    </ol>
    <pre dir="ltr" class="wplm-code">slug: <?php echo esc_html(SmsService::ADDON_SLUG); ?>
name: <?php echo esc_html(SmsService::ADDON_NAME); ?>
integration_code: <?php echo esc_html(SmsService::INTEGRATION_CODE); ?>
namespace: <?php echo esc_html(SmsService::REST_NAMESPACE); ?>
callback_url: <?php echo esc_html(SmsService::get_ippanel_callback_url()); ?></pre>
</div>

<div class="wplm-panel">
    <h2>نمونه ارسال پیامک در کدنویسی</h2>
    <pre dir="ltr" class="wplm-code">// ارسال پیامک متنی
$result = IPPanel_Core_Addon_Client::send_text_sms(
    'wp-plugin-market-manager',
    '09123456789',
    'متن پیام تستی'
);

// ارسال پیامک پترن
$result = IPPanel_Core_Addon_Client::send_pattern_sms(
    'wp-plugin-market-manager',
    '09123456789',
    'pattern-code',
    [
        'license_key' => 'XXXX-XXXX-XXXX-XXXX',
        'product'     => 'نام محصول',
        'domain'      => 'example.com',
    ]
);</pre>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
