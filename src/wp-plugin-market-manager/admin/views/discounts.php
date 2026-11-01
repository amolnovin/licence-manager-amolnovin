<?php
use WPLM\Repositories\CategoryRepository;
use WPLM\Repositories\DiscountRepository;
use WPLM\Repositories\ProductRepository;

defined('ABSPATH') || exit;
$title = 'مدیریت کدهای تخفیف';
$info_notice = 'کد تخفیف می‌تواند برای همه محصولات، یک محصول خاص یا یک دسته‌بندی خاص تعریف شود و در فرم خرید اختصاصی اعمال می‌شود.';
require WPLM_PATH . 'admin/views/header.php';
$repo = new DiscountRepository();
$product_repo = new ProductRepository();
$category_repo = new CategoryRepository();
$discounts = $repo->all();
$products = $product_repo->all(300);
$categories = $category_repo->all(300, true);
$edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$edit = $edit_id ? $repo->find($edit_id) : null;
?>
<div class="wplm-layout">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wplm-panel wplm-form">
        <input type="hidden" name="action" value="wplm_save_discount">
        <input type="hidden" name="id" value="<?php echo esc_attr($edit->id ?? 0); ?>">
        <?php wp_nonce_field('wplm_save_discount'); ?>
        <h2><?php echo $edit ? 'ویرایش کد تخفیف' : 'افزودن کد تخفیف'; ?></h2>
        <div class="wplm-two">
            <label>کد تخفیف<input name="code" dir="ltr" required value="<?php echo esc_attr($edit->code ?? ''); ?>" placeholder="SUMMER30"></label>
            <label>عنوان داخلی<input name="title" value="<?php echo esc_attr($edit->title ?? ''); ?>" placeholder="کمپین تابستان"></label>
        </div>
        <div class="wplm-two">
            <label>نوع تخفیف<select name="discount_type"><option value="percent" <?php selected($edit->discount_type ?? '', 'percent'); ?>>درصدی</option><option value="fixed" <?php selected($edit->discount_type ?? '', 'fixed'); ?>>مبلغ ثابت</option></select></label>
            <label>مقدار<input name="amount" type="number" step="0.01" min="0" required value="<?php echo esc_attr($edit->amount ?? ''); ?>" placeholder="مثلاً 20 یا 50000"></label>
        </div>
        <label>محصول خاص<select name="product_id"><option value="0">همه محصولات</option><?php foreach ($products as $product) : ?><option value="<?php echo esc_attr($product->id); ?>" <?php selected((int)($edit->product_id ?? 0), (int)$product->id); ?>><?php echo esc_html($product->name); ?></option><?php endforeach; ?></select></label>
        <label>دسته‌بندی خاص<select name="category_id"><option value="0">همه دسته‌بندی‌ها</option><?php foreach ($categories as $cat) : ?><option value="<?php echo esc_attr($cat->id); ?>" <?php selected((int)($edit->category_id ?? 0), (int)$cat->id); ?>><?php echo esc_html($cat->name); ?></option><?php endforeach; ?></select></label>
        <div class="wplm-two">
            <label>تاریخ شروع<input name="starts_at" type="date" value="<?php echo esc_attr(!empty($edit->starts_at) ? gmdate('Y-m-d', strtotime($edit->starts_at)) : ''); ?>"></label>
            <label>تاریخ پایان<input name="ends_at" type="date" value="<?php echo esc_attr(!empty($edit->ends_at) ? gmdate('Y-m-d', strtotime($edit->ends_at)) : ''); ?>"></label>
        </div>
        <div class="wplm-two">
            <label>سقف استفاده، صفر یعنی نامحدود<input name="usage_limit" type="number" min="0" value="<?php echo esc_attr($edit->usage_limit ?? 0); ?>"></label>
            <label>وضعیت<select name="status"><option value="active" <?php selected($edit->status ?? '', 'active'); ?>>فعال</option><option value="draft" <?php selected($edit->status ?? '', 'draft'); ?>>غیرفعال</option></select></label>
        </div>
        <button class="button button-primary button-hero">ذخیره کد تخفیف</button>
        <?php if ($edit) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'wplm-discounts'], admin_url('admin.php'))); ?>">افزودن جدید</a><?php endif; ?>
    </form>

    <div class="wplm-panel">
        <h2>لیست کدهای تخفیف</h2>
        <div class="wplm-table-wrap">
            <table class="widefat striped">
                <thead><tr><th>کد</th><th>نوع/مقدار</th><th>محدوده</th><th>زمان</th><th>استفاده</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($discounts as $item) : ?>
                    <tr>
                        <td><code dir="ltr"><?php echo esc_html($item->code); ?></code><br><?php echo esc_html($item->title); ?></td>
                        <td><?php echo 'percent' === $item->discount_type ? 'درصدی' : 'مبلغ ثابت'; ?>: <strong><?php echo esc_html(number_format_i18n((float)$item->amount)); ?></strong></td>
                        <td>
                            محصول: <?php echo esc_html($item->product_name ?: 'همه'); ?><br>
                            دسته: <?php echo esc_html($item->category_name ?: 'همه'); ?>
                        </td>
                        <td><?php echo !empty($item->starts_at) ? esc_html(mysql2date('Y/m/d', $item->starts_at)) : '—'; ?> تا <?php echo !empty($item->ends_at) ? esc_html(mysql2date('Y/m/d', $item->ends_at)) : '—'; ?></td>
                        <td><?php echo esc_html((int)$item->used_count); ?> / <?php echo !empty($item->usage_limit) ? esc_html((int)$item->usage_limit) : '∞'; ?></td>
                        <td><span class="wplm-status <?php echo esc_attr($item->status); ?>"><?php echo 'active' === $item->status ? 'فعال' : 'غیرفعال'; ?></span></td>
                        <td class="wplm-actions">
                            <a class="button wplm-icon-btn" title="ویرایش کد تخفیف" aria-label="ویرایش کد تخفیف" href="<?php echo esc_url(add_query_arg(['page'=>'wplm-discounts','edit'=>(int)$item->id], admin_url('admin.php'))); ?>"><span class="dashicons dashicons-edit"></span></a>
                            <a class="button wplm-icon-btn is-danger" title="حذف کد تخفیف" aria-label="حذف کد تخفیف" onclick="return confirm('حذف شود؟')" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_delete_discount','id'=>(int)$item->id], admin_url('admin-post.php')), 'wplm_delete_discount')); ?>"><span class="dashicons dashicons-trash"></span></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$discounts) : ?><tr><td colspan="7">هنوز کد تخفیفی ثبت نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
