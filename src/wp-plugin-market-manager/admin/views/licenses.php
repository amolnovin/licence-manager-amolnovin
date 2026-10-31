<?php
use WPLM\Repositories\LicenseRepository;
use WPLM\Repositories\ProductRepository;
use WPLM\Security;

defined('ABSPATH') || exit;
$title = 'مدیریت پیشرفته لایسنس‌ها';
$info_notice = 'ایجاد و ویرایش لایسنس‌ها اکنون به صورت مودال انجام می‌شود تا لیست لایسنس‌ها همیشه محور صفحه باشد.';
require WPLM_PATH . 'admin/views/header.php';

$license_repo = new LicenseRepository();
$product_repo = new ProductRepository();
$products = $product_repo->all(300);
$filter_status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
$filter_product = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$licenses = $license_repo->all(300, [
    'status' => $filter_status,
    'product_id' => $filter_product,
    'search' => $search,
]);
$status_labels = [
    'active' => 'فعال',
    'inactive' => 'غیرفعال',
    'suspended' => 'مسدود',
    'expired' => 'منقضی',
];
?>
<div class="wplm-panel">
    <div class="wplm-panel-head">
        <div>
            <h2>لایسنس‌ها</h2>
            <p>لیست لایسنس‌ها، دامنه‌های فعال، وضعیت اعتبار و عملیات مدیریتی.</p>
        </div>
        <button type="button" class="button button-primary button-hero" data-wplm-open-license-modal>ایجاد لایسنس دستی</button>
    </div>

    <form method="get" class="wplm-filter-form">
        <input type="hidden" name="page" value="wplm-licenses">
        <input name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو کلید، ایمیل، موبایل، دامنه">
        <select name="product_id"><option value="0">همه محصولات</option><?php foreach ($products as $product) : ?><option value="<?php echo esc_attr($product->id); ?>" <?php selected($filter_product, (int)$product->id); ?>><?php echo esc_html($product->name); ?></option><?php endforeach; ?></select>
        <select name="status"><option value="">همه وضعیت‌ها</option><?php foreach ($status_labels as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filter_status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
        <button class="button">فیلتر</button>
    </form>

    <div class="wplm-table-wrap">
        <table class="widefat striped">
            <thead><tr><th>لایسنس</th><th>محصول</th><th>مشتری</th><th>دامنه‌ها</th><th>اعتبار</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($licenses as $license) : $domains = $license_repo->domains($license); ?>
                <tr>
                    <td dir="ltr"><code><?php echo esc_html(Security::mask_key($license->license_key)); ?></code><br><small>#<?php echo esc_html((int)$license->id); ?></small></td>
                    <td><?php echo esc_html($license->product_name ?: '#' . $license->product_id); ?><br><code dir="ltr"><?php echo esc_html($license->product_slug); ?></code></td>
                    <td><code dir="ltr"><?php echo esc_html($license->customer_email ?: '—'); ?></code><br><code dir="ltr"><?php echo esc_html($license->customer_mobile ?: '—'); ?></code></td>
                    <td>
                        <small><?php echo esc_html(count($domains)); ?> از <?php echo esc_html((int)$license->allowed_domains); ?></small><br>
                        <?php foreach ($domains as $domain) : ?>
                            <span class="wplm-domain-chip" dir="ltr"><?php echo esc_html($domain); ?>
                                <a title="حذف دامنه" onclick="return confirm('دامنه حذف شود؟')" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_remove_license_domain','id'=>(int)$license->id,'domain'=>$domain], admin_url('admin-post.php')), 'wplm_license_action')); ?>">×</a>
                            </span>
                        <?php endforeach; ?>
                        <?php if (!$domains) : ?>—<?php endif; ?>
                    </td>
                    <td><?php echo !empty($license->expires_at) ? esc_html(mysql2date('Y/m/d', $license->expires_at)) : 'نامحدود'; ?></td>
                    <td><span class="wplm-status <?php echo esc_attr($license->status); ?>"><?php echo esc_html($status_labels[$license->status] ?? $license->status); ?></span></td>
                    <td class="wplm-actions">
                        <button type="button" class="button" data-wplm-edit-license
                            data-id="<?php echo esc_attr($license->id); ?>"
                            data-product_id="<?php echo esc_attr($license->product_id); ?>"
                            data-license_key="<?php echo esc_attr($license->license_key); ?>"
                            data-customer_email="<?php echo esc_attr($license->customer_email); ?>"
                            data-customer_mobile="<?php echo esc_attr($license->customer_mobile); ?>"
                            data-allowed_domains="<?php echo esc_attr($license->allowed_domains); ?>"
                            data-expires_at="<?php echo esc_attr(!empty($license->expires_at) ? gmdate('Y-m-d', strtotime($license->expires_at)) : ''); ?>"
                            data-status="<?php echo esc_attr($license->status); ?>"
                            data-domains="<?php echo esc_attr(implode("\n", $domains)); ?>">ویرایش</button>
                        <?php if ('active' !== $license->status) : ?>
                            <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_set_license_status','id'=>(int)$license->id,'status'=>'active'], admin_url('admin-post.php')), 'wplm_license_action')); ?>">فعال</a>
                        <?php else : ?>
                            <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_set_license_status','id'=>(int)$license->id,'status'=>'inactive'], admin_url('admin-post.php')), 'wplm_license_action')); ?>">غیرفعال</a>
                        <?php endif; ?>
                        <a class="button wplm-danger" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_set_license_status','id'=>(int)$license->id,'status'=>'suspended'], admin_url('admin-post.php')), 'wplm_license_action')); ?>">مسدود</a>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_resend_license','id'=>(int)$license->id], admin_url('admin-post.php')), 'wplm_license_action')); ?>">ارسال</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$licenses) : ?><tr><td colspan="7">لایسنسی یافت نشد.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="wplm-modal" id="wplm-license-modal" aria-hidden="true">
    <div class="wplm-modal-backdrop" data-wplm-close-license-modal></div>
    <div class="wplm-modal-box" role="dialog" aria-modal="true">
        <button type="button" class="wplm-modal-close" data-wplm-close-license-modal>×</button>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wplm-form" id="wplm-license-form">
            <input type="hidden" name="action" value="wplm_save_license">
            <input type="hidden" name="id" value="0">
            <?php wp_nonce_field('wplm_save_license'); ?>
            <h2 data-wplm-license-modal-title>ایجاد لایسنس دستی</h2>
            <label>محصول
                <select name="product_id" required>
                    <option value="0">انتخاب محصول</option>
                    <?php foreach ($products as $product) : ?>
                        <option value="<?php echo esc_attr($product->id); ?>"><?php echo esc_html($product->name); ?> - <?php echo esc_html($product->slug); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>کلید لایسنس<input name="license_key" dir="ltr" placeholder="در صورت خالی بودن خودکار ساخته می‌شود"></label>
            <div class="wplm-two">
                <label>ایمیل مشتری<input name="customer_email" type="email" dir="ltr"></label>
                <label>موبایل مشتری<input name="customer_mobile" dir="ltr"></label>
            </div>
            <div class="wplm-two">
                <label>تعداد دامنه مجاز<input name="allowed_domains" type="number" min="1" value="1"></label>
                <label>تاریخ انقضا<input name="expires_at" type="date"></label>
            </div>
            <label>وضعیت
                <select name="status">
                    <?php foreach ($status_labels as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>دامنه‌های فعال، هر دامنه در یک خط<textarea name="domains" rows="5" dir="ltr" placeholder="example.com&#10;client.ir"></textarea></label>
            <div class="wplm-modal-actions">
                <button class="button button-primary button-hero">ذخیره لایسنس</button>
                <button class="button" type="button" data-wplm-close-license-modal>انصراف</button>
                <span class="wplm-modal-hint">برای لایسنس جدید اگر کلید را خالی بگذارید، خودکار تولید می‌شود.</span>
            </div>
        </form>
    </div>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
