<?php
use WPLM\Repositories\LicenseRepository;

defined('ABSPATH') || exit;
$title = 'مدیریت درخواست‌های لایسنس';
$info_notice = 'درخواست‌ها می‌توانند دستی، از API آنلاین یا به صورت خودکار پس از پرداخت زیبال ثبت شوند.';
require WPLM_PATH . 'admin/views/header.php';
$requests = (new LicenseRepository())->requests();
?>
<div class="wplm-panel">
    <h2>درخواست‌ها</h2>
    <p>مسیر ثبت درخواست آنلاین: <code dir="ltr">/wp-json/wplm/v1/license/request</code></p>
    <div class="wplm-table-wrap">
        <table class="widefat striped">
            <thead><tr><th>محصول</th><th>نام مشتری</th><th>ایمیل</th><th>موبایل</th><th>دامنه</th><th>منبع</th><th>سفارش</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
            <tbody>
                <?php foreach ($requests as $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item->product_name ?: '#' . $item->product_id); ?></td>
                        <td><?php echo esc_html($item->customer_name); ?></td>
                        <td dir="ltr"><?php echo esc_html($item->customer_email); ?></td>
                        <td dir="ltr"><?php echo esc_html($item->customer_mobile); ?></td>
                        <td dir="ltr"><?php echo esc_html($item->domain); ?></td>
                        <td><?php echo esc_html(['zibal'=>'زیبال','manual'=>'دستی','api'=>'API'][$item->source] ?? $item->source); ?></td>
                        <td dir="ltr"><?php echo !empty($item->order_id) ? '#' . esc_html((int) $item->order_id) : '—'; ?></td>
                        <td><span class="wplm-status <?php echo esc_attr($item->status); ?>"><?php echo esc_html(['pending'=>'در انتظار','approved'=>'تایید شده','rejected'=>'رد شده'][$item->status] ?? $item->status); ?></span></td>
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $item->created_at)); ?></td>
                        <td class="wplm-actions">
                            <?php if ('pending' === $item->status) : ?>
                                <a class="button wplm-icon-btn is-success" title="تایید و صدور لایسنس" aria-label="تایید و صدور لایسنس" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_approve_request','id'=>(int)$item->id], admin_url('admin-post.php')), 'wplm_request_action')); ?>"><span class="dashicons dashicons-yes-alt"></span></a>
                                <a class="button wplm-icon-btn is-danger" title="رد درخواست" aria-label="رد درخواست" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_reject_request','id'=>(int)$item->id], admin_url('admin-post.php')), 'wplm_request_action')); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                            <?php else : ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$requests) : ?><tr><td colspan="10">هنوز درخواستی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
