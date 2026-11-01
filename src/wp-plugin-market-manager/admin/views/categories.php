<?php
use WPLM\Repositories\CategoryRepository;

defined('ABSPATH') || exit;
$title = 'دسته‌بندی افزونه و قالب';
$info_notice = 'برای نظم‌دهی صفحه فروشگاه، دسته‌بندی‌های افزونه و قالب را اینجا مدیریت کنید.';
require WPLM_PATH . 'admin/views/header.php';
$repo = new CategoryRepository();
$categories = $repo->all();
$edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$edit = $edit_id ? $repo->find($edit_id) : null;
?>
<div class="wplm-layout">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wplm-panel wplm-form">
        <input type="hidden" name="action" value="wplm_save_category">
        <input type="hidden" name="id" value="<?php echo esc_attr($edit->id ?? 0); ?>">
        <?php wp_nonce_field('wplm_save_category'); ?>
        <h2><?php echo $edit ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی'; ?></h2>
        <label>نام دسته‌بندی<input name="name" required value="<?php echo esc_attr($edit->name ?? ''); ?>"></label>
        <label>شناسه یکتا<input name="slug" dir="ltr" value="<?php echo esc_attr($edit->slug ?? ''); ?>" placeholder="seo-plugins"></label>
        <div class="wplm-two">
            <label>نوع<select name="type"><option value="all" <?php selected($edit->type ?? '', 'all'); ?>>عمومی</option><option value="plugin" <?php selected($edit->type ?? '', 'plugin'); ?>>افزونه</option><option value="theme" <?php selected($edit->type ?? '', 'theme'); ?>>قالب</option></select></label>
            <label>وضعیت<select name="status"><option value="active" <?php selected($edit->status ?? '', 'active'); ?>>فعال</option><option value="draft" <?php selected($edit->status ?? '', 'draft'); ?>>غیرفعال</option></select></label>
        </div>
        <label>توضیحات<textarea name="description" rows="4"><?php echo esc_textarea($edit->description ?? ''); ?></textarea></label>
        <button class="button button-primary button-hero">ذخیره دسته‌بندی</button>
    </form>

    <div class="wplm-panel">
        <h2>لیست دسته‌بندی‌ها</h2>
        <div class="wplm-table-wrap">
            <table class="widefat striped">
                <thead><tr><th>نام</th><th>شناسه</th><th>نوع</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $cat) : ?>
                    <tr>
                        <td><?php echo esc_html($cat->name); ?></td>
                        <td dir="ltr"><code><?php echo esc_html($cat->slug); ?></code></td>
                        <td><?php echo esc_html(['all'=>'عمومی','plugin'=>'افزونه','theme'=>'قالب'][$cat->type] ?? $cat->type); ?></td>
                        <td><span class="wplm-status <?php echo esc_attr($cat->status); ?>"><?php echo 'active' === $cat->status ? 'فعال' : 'غیرفعال'; ?></span></td>
                        <td>
                            <a class="button wplm-icon-btn" title="ویرایش دسته‌بندی" aria-label="ویرایش دسته‌بندی" href="<?php echo esc_url(add_query_arg(['page'=>'wplm-categories','edit'=>(int)$cat->id], admin_url('admin.php'))); ?>"><span class="dashicons dashicons-edit"></span></a>
                            <a class="button wplm-icon-btn is-danger" title="حذف دسته‌بندی" aria-label="حذف دسته‌بندی" onclick="return confirm('حذف شود؟')" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_delete_category','id'=>(int)$cat->id], admin_url('admin-post.php')), 'wplm_delete_category')); ?>"><span class="dashicons dashicons-trash"></span></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$categories) : ?><tr><td colspan="5">هنوز دسته‌بندی ثبت نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
