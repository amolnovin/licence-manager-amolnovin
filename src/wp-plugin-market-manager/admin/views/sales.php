<?php
use WPLM\Repositories\OrderRepository;

defined('ABSPATH') || exit;
$title = 'سفارش‌ها و فروش اختصاصی';
$info_notice = 'فروش این سامانه مستقل از افزونه فروشگاهی دیگر است و پرداخت‌ها از طریق درگاه زیبال انجام می‌شود. کدهای تخفیف نیز روی مبلغ نهایی سفارش اعمال می‌شوند.';
require WPLM_PATH . 'admin/views/header.php';
$order_repo = new OrderRepository();
$orders = $order_repo->all();
$stats = $order_repo->stats();
$status_label = ['pending'=>'در انتظار پرداخت','paid'=>'پرداخت‌شده','failed'=>'ناموفق','canceled'=>'لغو شده'];
?>
<div class="wplm-grid wplm-stats">
    <div class="wplm-card"><span>فروش امروز</span><strong><?php echo esc_html(number_format_i18n($stats['today_revenue'])); ?></strong></div>
    <div class="wplm-card"><span>فروش ماه</span><strong><?php echo esc_html(number_format_i18n($stats['month_revenue'])); ?></strong></div>
    <div class="wplm-card"><span>کل فروش</span><strong><?php echo esc_html(number_format_i18n($stats['total_revenue'])); ?></strong></div>
    <div class="wplm-card"><span>جمع تخفیف‌ها</span><strong><?php echo esc_html(number_format_i18n($stats['discount_total'])); ?></strong></div>
</div>
<div class="wplm-panel">
    <h2>فرآیند فروش اختصاصی</h2>
    <ol class="wplm-roadmap">
        <li>کاربر از صفحه فروشگاه، افزونه یا قالب را مشاهده می‌کند.</li>
        <li>در تک‌صفحه معرفی، فرم خرید و در صورت نیاز کد تخفیف را تکمیل می‌کند.</li>
        <li>سفارش داخلی با مبلغ نهایی ایجاد و کاربر به درگاه پرداخت زیبال هدایت می‌شود.</li>
        <li>پس از پرداخت موفق، تراکنش با زیبال verify می‌شود.</li>
        <li>لایسنس ایجاد شده و در حساب کاربری مشتری نمایش داده می‌شود.</li>
    </ol>
</div>
<div class="wplm-panel">
    <h2>لیست سفارش‌ها</h2>
    <div class="wplm-table-wrap">
        <table class="widefat striped">
            <thead><tr><th>شماره</th><th>محصول</th><th>مشتری</th><th>موبایل</th><th>مبلغ</th><th>تخفیف</th><th>وضعیت</th><th>Track ID</th><th>Ref</th><th>تاریخ</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order) : ?>
                <tr>
                    <td>#<?php echo esc_html((int) $order->id); ?></td>
                    <td><?php echo esc_html($order->product_name ?: '#' . $order->product_id); ?></td>
                    <td><?php echo esc_html($order->customer_name); ?><br><code dir="ltr"><?php echo esc_html($order->customer_email); ?></code></td>
                    <td dir="ltr"><?php echo esc_html($order->customer_mobile); ?></td>
                    <td>
                        <?php if (!empty($order->discount_amount)) : ?><del><?php echo esc_html(number_format_i18n((float) $order->original_amount)); ?></del><br><?php endif; ?>
                        <strong><?php echo esc_html(number_format_i18n((float) $order->amount)); ?> <?php echo esc_html($order->currency); ?></strong>
                    </td>
                    <td><?php echo !empty($order->discount_code) ? '<code dir="ltr">' . esc_html($order->discount_code) . '</code><br>' . esc_html(number_format_i18n((float)$order->discount_amount)) : '—'; ?></td>
                    <td><span class="wplm-status <?php echo esc_attr($order->status); ?>"><?php echo esc_html($status_label[$order->status] ?? $order->status); ?></span></td>
                    <td dir="ltr"><?php echo esc_html($order->track_id ?: '—'); ?></td>
                    <td dir="ltr"><?php echo esc_html($order->ref_number ?: '—'); ?></td>
                    <td><?php echo esc_html(mysql2date('Y/m/d H:i', $order->created_at)); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders) : ?><tr><td colspan="10">هنوز سفارشی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
