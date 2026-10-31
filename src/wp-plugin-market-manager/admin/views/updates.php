<?php
use WPLM\Repositories\ProductRepository;
use WPLM\Repositories\VersionRepository;
use WPLM\Services\PackageService;

defined('ABSPATH') || exit;
$title = 'مدیریت نسخه‌ها و بروزرسانی امن';
$info_notice = 'بروزرسانی افزونه‌های مشتری از بخش «افزونه‌ها» در پیشخوان وردپرس انجام می‌شود؛ این صفحه فقط نسخه‌ها و فایل ZIP امن را مدیریت می‌کند.';
require WPLM_PATH . 'admin/views/header.php';
$product_repo = new ProductRepository();
$version_repo = new VersionRepository();
$products = $product_repo->all(300);
$selected_product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : (!empty($products[0]->id) ? (int) $products[0]->id : 0);
$selected_product = $selected_product_id ? $product_repo->find($selected_product_id) : null;
$versions = $selected_product_id ? $version_repo->by_product($selected_product_id) : [];
PackageService::ensure_secure_dir();
?>
<div class="wplm-layout">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="wplm-panel wplm-form">
        <input type="hidden" name="action" value="wplm_save_version">
        <?php wp_nonce_field('wplm_save_version'); ?>
        <h2>افزودن نسخه جدید</h2>
        <label>محصول
            <select name="product_id" required onchange="if(this.value){location.href='<?php echo esc_js(admin_url('admin.php?page=wplm-updates&product_id=')); ?>'+this.value}">
                <?php foreach ($products as $product) : ?>
                    <option value="<?php echo esc_attr($product->id); ?>" <?php selected($selected_product_id, (int) $product->id); ?>><?php echo esc_html($product->name); ?> - <?php echo esc_html($product->slug); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="wplm-two">
            <label>شماره نسخه<input name="version" dir="ltr" required placeholder="1.2.0"></label>
            <label>فایل ZIP نسخه<input name="package_zip" type="file" accept=".zip,application/zip" required></label>
        </div>
        <div class="wplm-two">
            <label>حداقل وردپرس<input name="requires_wp" dir="ltr" placeholder="6.2"></label>
            <label>تست‌شده تا وردپرس<input name="tested_wp" dir="ltr" placeholder="6.6"></label>
        </div>
        <label>حداقل PHP<input name="requires_php" dir="ltr" placeholder="7.4"></label>
        <label>تغییرات نسخه / Changelog<textarea name="changelog" rows="5" placeholder="تغییرات، رفع باگ‌ها و امکانات جدید"></textarea></label>
        <label class="wplm-check"><input type="checkbox" name="is_current" value="1" checked> این نسخه، نسخه فعلی و قابل بروزرسانی باشد</label>
        <button class="button button-primary button-hero">آپلود و ذخیره نسخه</button>
        <p class="description">فایل در پوشه امن <code dir="ltr">wp-content/uploads/wplm-secure-packages</code> ذخیره می‌شود و لینک مستقیم عمومی ندارد.</p>
    </form>

    <div class="wplm-panel">
        <h2>نسخه‌های محصول</h2>
        <?php if ($selected_product) : ?>
            <p>محصول انتخاب‌شده: <strong><?php echo esc_html($selected_product->name); ?></strong> <code dir="ltr"><?php echo esc_html($selected_product->slug); ?></code></p>
        <?php endif; ?>
        <div class="wplm-table-wrap">
            <table class="widefat striped">
                <thead><tr><th>نسخه</th><th>فایل</th><th>سازگاری</th><th>دانلودها</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($versions as $version) : ?>
                    <tr>
                        <td dir="ltr"><strong><?php echo esc_html($version->version); ?></strong></td>
                        <td><code dir="ltr"><?php echo esc_html($version->file_name ?: '—'); ?></code><br><small><?php echo esc_html(size_format((int) $version->file_size)); ?></small></td>
                        <td>WP: <?php echo esc_html($version->requires_wp ?: '—'); ?> / <?php echo esc_html($version->tested_wp ?: '—'); ?><br>PHP: <?php echo esc_html($version->requires_php ?: '—'); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $version->download_count)); ?></td>
                        <td><span class="wplm-status <?php echo $version->is_current ? 'active' : 'draft'; ?>"><?php echo $version->is_current ? 'نسخه فعلی' : 'آرشیو'; ?></span></td>
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $version->created_at)); ?></td>
                        <td class="wplm-actions">
                            <?php if (!$version->is_current) : ?>
                                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_make_current_version','id'=>(int)$version->id], admin_url('admin-post.php')), 'wplm_version_action')); ?>">انتخاب به عنوان فعلی</a>
                            <?php endif; ?>
                            <a class="button wplm-danger" onclick="return confirm('نسخه و فایل ZIP حذف شود؟')" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_delete_version','id'=>(int)$version->id], admin_url('admin-post.php')), 'wplm_version_action')); ?>">حذف</a>
                        </td>
                    </tr>
                    <?php if (!empty($version->changelog)) : ?>
                        <tr><td colspan="7"><strong>Changelog:</strong><br><?php echo wp_kses_post(wpautop((string) $version->changelog)); ?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$versions) : ?><tr><td colspan="7">هنوز نسخه‌ای برای این محصول ثبت نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="wplm-panel">
    <h2>API بروزرسانی افزونه‌ها</h2>
    <p>افزونه مشتری باید از کلاس updater استفاده کند. بررسی نسخه از مسیر زیر انجام می‌شود و در صورت وجود نسخه جدید، وردپرس آن را در صفحه افزونه‌ها نمایش می‌دهد.</p>
    <pre dir="ltr" class="wplm-code">POST <?php echo esc_html(rest_url('wplm/v1/updates/check')); ?>
{
  "product_slug": "<?php echo esc_html($selected_product ? $selected_product->slug : 'my-plugin'); ?>",
  "plugin_file": "my-plugin/my-plugin.php",
  "version": "1.0.0",
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "domain": "client-site.com"
}</pre>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
