<?php
use WPLM\Services\SmsService;

defined('ABSPATH') || exit;
$wplm_info = '';
if (!empty($info_notice)) {
    $wplm_info = (string) $info_notice;
} elseif (!SmsService::has_ippanel_core() && isset($_GET['page']) && 'wplm-sms' === $_GET['page']) {
    $wplm_info = 'برای ارسال پیامک از طریق IPPanel Core، ابتدا افزونه ippanel-core را نصب و فعال کنید.';
}
?>
<div class="wrap wplm-wrap" dir="rtl">
    <div class="wplm-hero">
        <div class="wplm-hero-content">
            <span class="wplm-badge">سامانه فروش و لایسنس افزونه و قالب</span>
            <h1><?php echo esc_html($title ?? 'سامانه فروش و لایسنس'); ?></h1>
            <p>مدیریت فروش، لایسنس آنلاین، بروزرسانی امن و پیامک برای محصولات وردپرسی.</p>
            <?php if ($wplm_info) : ?>
                <div class="notice notice-info wplm-hero-notice"><p><?php echo esc_html($wplm_info); ?></p></div>
            <?php endif; ?>
        </div>
        <div class="wplm-version">نسخه <?php echo esc_html(WPLM_VERSION); ?></div>
    </div>
    <div class="wplm-notices">
        <?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div><?php endif; ?>
        <?php if (!empty($_GET['deleted'])) : ?><div class="notice notice-success is-dismissible"><p>حذف شد.</p></div><?php endif; ?>
        <?php if (!empty($_GET['resent'])) : ?><div class="notice notice-success is-dismissible"><p>اطلاعات لایسنس دوباره ارسال شد.</p></div><?php endif; ?>
        <?php if (!empty($_GET['approved'])) : ?><div class="notice notice-success is-dismissible"><p>درخواست تایید و لایسنس ایجاد شد.</p></div><?php endif; ?>
        <?php if (!empty($_GET['rejected'])) : ?><div class="notice notice-warning is-dismissible"><p>درخواست رد شد.</p></div><?php endif; ?>
        <?php if (!empty($_GET['wplm_error'])) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['wplm_error'])); ?></p></div><?php endif; ?>
    </div>
