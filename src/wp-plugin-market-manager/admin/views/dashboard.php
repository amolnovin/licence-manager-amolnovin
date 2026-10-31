<?php
use WPLM\Repositories\OrderRepository;
use WPLM\Repositories\ProductRepository;

defined('ABSPATH') || exit;
$title = 'داشبورد سامانه فروش و لایسنس';
$info_notice = 'افزونه اکنون فروش اختصاصی، زیبال، کد تخفیف، لایسنس، دانلود امن، بروزرسانی افزونه‌ها و حساب کاربری مشتری را پوشش می‌دهد.';
require WPLM_PATH . 'admin/views/header.php';
$counts = (new ProductRepository())->counts();
$stats = (new OrderRepository())->stats();
?>
<div class="wplm-grid wplm-stats">
    <div class="wplm-card"><span>کل محصولات</span><strong><?php echo esc_html($counts['products']); ?></strong></div>
    <div class="wplm-card"><span>محصولات فعال</span><strong><?php echo esc_html($counts['active_products']); ?></strong></div>
    <div class="wplm-card"><span>سفارش‌ها</span><strong><?php echo esc_html($counts['orders']); ?></strong></div>
    <div class="wplm-card"><span>فروش موفق</span><strong><?php echo esc_html($counts['paid_orders']); ?></strong></div>
    <div class="wplm-card"><span>درآمد کل</span><strong><?php echo esc_html(number_format_i18n($stats['total_revenue'])); ?></strong></div>
    <div class="wplm-card"><span>تخفیف اعمال‌شده</span><strong><?php echo esc_html(number_format_i18n($stats['discount_total'])); ?></strong></div>
    <div class="wplm-card"><span>لایسنس‌ها</span><strong><?php echo esc_html($counts['licenses']); ?></strong></div>
    <div class="wplm-card"><span>درخواست‌های در انتظار</span><strong><?php echo esc_html($counts['pending_requests']); ?></strong></div>
</div>
<div class="wplm-panel">
    <h2>وضعیت پیاده‌سازی</h2>
    <ol class="wplm-roadmap">
        <li><b>هسته امن افزونه:</b> انجام شد.</li>
        <li><b>محصولات و دسته‌بندی‌ها:</b> انجام شد.</li>
        <li><b>فروش اختصاصی و زیبال:</b> انجام شد.</li>
        <li><b>کدهای تخفیف و کمپین فروش:</b> انجام شد؛ درصدی/مبلغ ثابت، محدودیت محصول/دسته، تاریخ و سقف استفاده.</li>
        <li><b>حساب کاربری خریدها:</b> انجام شد؛ نمایش سفارش‌ها، لایسنس‌ها و دانلود امن.</li>
        <li><b>پیامک IPPanel Core:</b> انجام شد.</li>
        <li><b>بروزرسانی امن افزونه:</b> انجام شد؛ مدیریت نسخه، آپلود ZIP امن، لینک دانلود امضاشده و updater برای صفحه افزونه‌های وردپرس.</li>
        <li><b>مدیریت پیشرفته لایسنس‌ها:</b> انجام شد؛ ایجاد دستی، ویرایش، تمدید، مدیریت دامنه‌ها، تغییر وضعیت و ارسال مجدد اطلاعات.</li>
    </ol>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
