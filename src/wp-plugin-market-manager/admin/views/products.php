<?php
use WPLM\Repositories\CategoryRepository;
use WPLM\Repositories\ProductRepository;

defined('ABSPATH') || exit;
$title = 'ثبت و مدیریت افزونه و قالب';
$info_notice = 'برای افزودن محصول جدید روی دکمه افزودن کلیک کنید؛ فرم به صورت مودال و ایجکسی ذخیره می‌شود.';
require WPLM_PATH . 'admin/views/header.php';
$repo = new ProductRepository();
$cat_repo = new CategoryRepository();
$products = $repo->all();
$categories = $cat_repo->all(200, true);
?>
<div class="wplm-panel">
    <div class="wplm-panel-head">
        <div>
            <h2>لیست محصولات</h2>
            <p>محصولات فعال در فروشگاه نمایش داده می‌شوند.</p>
        </div>
        <button type="button" class="button button-primary button-hero" data-wplm-open-product-modal>افزودن محصول جدید</button>
    </div>
    <div class="wplm-table-wrap">
        <table class="widefat striped">
            <thead><tr><th>تصویر</th><th>نام</th><th>شناسه</th><th>نوع</th><th>نسخه</th><th>قیمت</th><th>دسته</th><th>نمایش فرانت</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
                <?php foreach ($products as $product) : $cat = !empty($product->category_id) ? $cat_repo->find((int)$product->category_id) : null; ?>
                    <tr>
                        <td><?php if (!empty($product->cover_url)) : ?><img class="wplm-thumb" src="<?php echo esc_url($product->cover_url); ?>" alt=""><?php else : ?><span class="wplm-thumb empty">—</span><?php endif; ?></td>
                        <td><?php echo esc_html($product->name); ?></td>
                        <td dir="ltr"><code><?php echo esc_html($product->slug); ?></code></td>
                        <td><?php echo 'theme' === $product->type ? 'قالب' : 'افزونه'; ?></td>
                        <td dir="ltr"><?php echo esc_html($product->version); ?></td>
                        <td><?php echo esc_html(number_format_i18n((float) $product->price)); ?> <?php echo esc_html($product->currency); ?></td>
                        <td><?php echo $cat ? esc_html($cat->name) : '—'; ?></td>
                        <td><span class="wplm-status <?php echo !empty($product->is_visible) ? 'active' : 'draft'; ?>"><?php echo !empty($product->is_visible) ? 'نمایش داده شود' : 'مخفی از فرانت'; ?></span></td>
                        <td><?php $lm = $product->license_mode ?? 'required'; ?><span class="wplm-status <?php echo esc_attr($product->status); ?>"><?php echo 'active' === $product->status ? ('none' === $lm ? 'فعال بدون لایسنس' : 'فعال لایسنس‌دار') : 'پیش‌نویس'; ?></span></td>
                        <td class="wplm-actions">
                            <button type="button" class="button" data-wplm-edit-product
                                data-id="<?php echo esc_attr($product->id); ?>"
                                data-category_id="<?php echo esc_attr($product->category_id); ?>"
                                data-name="<?php echo esc_attr($product->name); ?>"
                                data-slug="<?php echo esc_attr($product->slug); ?>"
                                data-type="<?php echo esc_attr($product->type); ?>"
                                data-cover_url="<?php echo esc_attr($product->cover_url); ?>"
                                data-short_description="<?php echo esc_attr($product->short_description); ?>"
                                data-description="<?php echo esc_attr($product->description); ?>"
                                data-version="<?php echo esc_attr($product->version); ?>"
                                data-price="<?php echo esc_attr($product->price); ?>"
                                data-currency="<?php echo esc_attr($product->currency); ?>"
                                data-status="<?php echo esc_attr($product->status); ?>"
                                data-license_mode="<?php echo esc_attr($product->license_mode ?? 'required'); ?>"
                                data-product_state="<?php echo esc_attr('active' === $product->status ? (('none' === ($product->license_mode ?? 'required')) ? 'active_none' : 'active_required') : 'draft'); ?>"
                                data-is_visible="<?php echo esc_attr(!empty($product->is_visible) ? 1 : 0); ?>">ویرایش</button>
                            <a class="button wplm-danger" onclick="return confirm('حذف شود؟')" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_delete_product','id'=>(int)$product->id], admin_url('admin-post.php')), 'wplm_delete_product')); ?>">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products) : ?><tr><td colspan="10">هنوز محصولی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="wplm-modal" id="wplm-product-modal" aria-hidden="true">
    <div class="wplm-modal-backdrop" data-wplm-close-product-modal></div>
    <div class="wplm-modal-box" role="dialog" aria-modal="true">
        <button type="button" class="wplm-modal-close" data-wplm-close-product-modal>×</button>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wplm-form" id="wplm-product-form">
            <input type="hidden" name="action" value="wplm_save_product">
            <input type="hidden" name="id" value="0">
            <?php wp_nonce_field('wplm_save_product'); ?>
            <h2 data-wplm-modal-title>افزودن محصول جدید</h2>
            <div class="wplm-two">
                <label>نام محصول<input name="name" required></label>
                <label>شناسه یکتا<input name="slug" dir="ltr" placeholder="my-plugin"></label>
            </div>
            <label>دسته‌بندی<select name="category_id"><option value="0">بدون دسته‌بندی</option><?php foreach ($categories as $cat) : ?><option value="<?php echo esc_attr($cat->id); ?>"><?php echo esc_html($cat->name); ?></option><?php endforeach; ?></select></label>
            <div class="wplm-two">
                <label>نوع<select name="type"><option value="plugin">افزونه</option><option value="theme">قالب</option></select></label>
                <label>وضعیت لایسنس محصول<select name="product_state"><option value="draft">پیش‌نویس</option><option value="active_required">فعال لایسنس‌دار</option><option value="active_none">فعال بدون لایسنس</option></select></label>
            </div>
            <label class="wplm-check"><input type="checkbox" name="is_visible" value="1" checked> نمایش محصول در فرانت فروشگاه و شورتکدها</label>
            <p class="description">اگر غیرفعال شود، محصول همچنان فعال می‌ماند و برای لایسنس/API/آپدیت قابل استفاده است، اما در فروشگاه و کاروسل فرانت نمایش داده نمی‌شود.</p>
            <div class="wplm-two">
                <label>نسخه<input name="version" dir="ltr" value="1.0.0"></label>
                <label>قیمت<input name="price" type="number" step="0.01" value="0"></label>
            </div>
            <div class="wplm-two">
                <label>واحد پول<input name="currency" value="IRT"></label>
                <label>تصویر شاخص
                    <div class="wplm-image-field">
                        <input name="cover_url" dir="ltr" placeholder="https://...">
                        <button type="button" class="button" data-wplm-media>انتخاب تصویر</button>
                    </div>
                </label>
            </div>
            <div class="wplm-cover-preview" data-wplm-cover-preview></div>
            <label>توضیح کوتاه<textarea name="short_description" rows="3"></textarea></label>
            <div class="wplm-editor-field">
                <label>معرفی کامل محصول</label>
                <?php wp_editor('', 'wplm_product_description_editor', [
                    'textarea_name' => 'description',
                    'textarea_rows' => 8,
                    'media_buttons' => true,
                    'teeny' => false,
                    'quicktags' => true,
                ]); ?>
                <p class="description">این بخش از ادیتور وردپرس پشتیبانی می‌کند و شورتکدها در صفحه محصول اجرا می‌شوند.</p>
            </div>
            <div class="wplm-modal-actions">
                <button class="button button-primary button-hero" type="submit">ذخیره محصول</button>
                <button class="button" type="button" data-wplm-close-product-modal>انصراف</button>
                <span class="wplm-ajax-message" data-wplm-ajax-message></span>
            </div>
        </form>
    </div>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
