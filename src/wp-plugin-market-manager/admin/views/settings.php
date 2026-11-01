<?php
use WPLM\Options;
use WPLM\Repositories\ProductRepository;
use WPLM\Services\SmsService;

defined('ABSPATH') || exit;
$title = 'تنظیمات سامانه';
$info_notice = 'تنظیمات فروش اختصاصی، درگاه زیبال، صفحات عمومی، لایسنس و پیامک از این بخش کنترل می‌شود.';
require WPLM_PATH . 'admin/views/header.php';
$settings = Options::get();
$featured_ids = Options::featured_product_ids();
$featured_ids = array_pad(array_slice($featured_ids, 0, 4), 4, 0);
$all_products = (new ProductRepository())->all(300);
$pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
$page_select = static function (string $name, int $selected) use ($pages): void {
    echo '<select name="' . esc_attr($name) . '"><option value="0">انتخاب صفحه</option>';
    foreach ($pages as $page) {
        echo '<option value="' . esc_attr($page->ID) . '" ' . selected($selected, (int) $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
    }
    echo '</select>';
};
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wplm-panel wplm-form wplm-settings">
    <input type="hidden" name="action" value="wplm_save_settings">
    <?php wp_nonce_field('wplm_save_settings'); ?>

    <h2>تنظیمات فروش اختصاصی</h2>
    <div class="wplm-two">
        <label>واحد پول پیش‌فرض<input name="currency" value="<?php echo esc_attr($settings['currency']); ?>"></label>
        <label>واحد مبلغ برای ارسال به درگاه<select name="price_unit"><option value="toman" <?php selected($settings['price_unit'], 'toman'); ?>>تومان؛ هنگام ارسال به زیبال × ۱۰ می‌شود</option><option value="rial" <?php selected($settings['price_unit'], 'rial'); ?>>ریال</option></select></label>
    </div>
    <div class="wplm-two">
        <label>صفحه لیست افزونه و قالب‌ها<?php $page_select('store_page_id', (int) $settings['store_page_id']); ?></label>
        <label>صفحه حساب کاربری<?php $page_select('account_page_id', (int) $settings['account_page_id']); ?></label>
    </div>
    <label>صفحه نتیجه خرید<?php $page_select('payment_result_page_id', (int) $settings['payment_result_page_id']); ?></label>

    <h2>افزونه‌های پرکاربرد</h2>
    <p class="description">حداکثر ۴ محصول را انتخاب کنید؛ این محصولات در بخش «افزونه‌های پرکاربرد» شورتکدها نمایش داده می‌شوند. برای حذف یک محصول، گزینه «انتخاب نشده» را بگذارید.</p>
    <div class="wplm-two">
        <?php foreach ($featured_ids as $index => $featured_id) : ?>
            <label>محصول پرکاربرد <?php echo esc_html((string) (count($featured_ids) - $index)); ?>
                <select name="featured_product_ids[]">
                    <option value="0">انتخاب نشده</option>
                    <?php foreach ($all_products as $product_option) : ?>
                        <?php if ('active' !== $product_option->status) { continue; } ?>
                        <option value="<?php echo esc_attr($product_option->id); ?>" <?php selected((int) $featured_id, (int) $product_option->id); ?>><?php echo esc_html($product_option->name); ?> — <?php echo esc_html($product_option->slug); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="wplm-two">
        <label>عنوان بخش<input name="popular_title" value="<?php echo esc_attr($settings['popular_title']); ?>" placeholder="افزونه‌های پرکاربرد"></label>
        <label>زیرنویس بخش<input name="popular_subtitle" value="<?php echo esc_attr($settings['popular_subtitle']); ?>"></label>
    </div>
    <label class="wplm-check"><input type="checkbox" name="popular_show_in_store" value="1" <?php checked($settings['popular_show_in_store'], '1'); ?>> نمایش خودکار این بخش در بالای صفحه فروشگاه (<code dir="ltr">[wplm_store]</code>)</label>
    <p class="description">برای نمایش در هر جای دیگر از شورتکد <code dir="ltr">[wplm_popular]</code> استفاده کنید. تا وقتی هیچ محصولی انتخاب نشده باشد، بخش نمایش داده نمی‌شود.</p>

    <h2>درگاه پرداخت</h2>
    <label>درگاه پیش‌فرض
        <select name="default_gateway">
            <option value="zibal" <?php selected($settings['default_gateway'], 'zibal'); ?>>زیبال</option>
            <option value="zarinpal" <?php selected($settings['default_gateway'], 'zarinpal'); ?>>زرین‌پال</option>
        </select>
    </label>
    <div class="wplm-two">
        <div class="wplm-inline-box">
            <h3>زیبال</h3>
            <label class="wplm-check"><input name="zibal_test_mode" type="checkbox" value="1" <?php checked($settings['zibal_test_mode'], '1'); ?>> حالت تست زیبال، merchant برابر <code>zibal</code> استفاده می‌شود</label>
            <label>Merchant ID زیبال<input name="zibal_merchant" dir="ltr" value="<?php echo esc_attr($settings['zibal_merchant']); ?>" placeholder="در حالت واقعی الزامی است"></label>
        </div>
        <div class="wplm-inline-box">
            <h3>زرین‌پال</h3>
            <label class="wplm-check"><input name="zarinpal_sandbox" type="checkbox" value="1" <?php checked($settings['zarinpal_sandbox'], '1'); ?>> حالت Sandbox زرین‌پال</label>
            <label>Merchant ID زرین‌پال<input name="zarinpal_merchant" dir="ltr" value="<?php echo esc_attr($settings['zarinpal_merchant']); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
        </div>
    </div>

    <h2>تنظیمات عمومی لایسنس</h2>
    <div class="wplm-two">
        <label>مدت اعتبار لایسنس، روز<input name="license_expire_days" type="number" min="1" value="<?php echo esc_attr($settings['license_expire_days']); ?>"></label>
        <label>تعداد دامنه مجاز<input name="allowed_domains" type="number" min="1" value="<?php echo esc_attr($settings['allowed_domains']); ?>"></label>
    </div>
    <label>ایمیل اطلاع‌رسانی مدیر<input name="notify_admin_email" type="email" value="<?php echo esc_attr($settings['notify_admin_email']); ?>"></label>

    <h2>تنظیمات پیامک IPPanel Core</h2>
    <div class="wplm-inline-status <?php echo SmsService::has_ippanel_token() ? 'ok' : 'warn'; ?>">
        <?php echo SmsService::has_ippanel_token() ? 'توکن دسترسی از IPPanel Core دریافت شده است.' : 'هنوز توکن دسترسی IPPanel Core برای این افزونه ثبت نشده است.'; ?>
    </div>
    <p>کد اتصال: <code dir="ltr"><?php echo esc_html(SmsService::INTEGRATION_CODE); ?></code></p>
    <p>آدرس callback دریافت کلید: <code dir="ltr"><?php echo esc_html(SmsService::get_ippanel_callback_url()); ?></code></p>

    <label class="wplm-check"><input name="sms_enabled" type="checkbox" value="1" <?php checked($settings['sms_enabled'], '1'); ?>> فعال‌سازی ارسال پیامک</label>
    <div class="wplm-two">
        <label>سرویس‌دهنده<select name="sms_provider"><option value="ippanel_core" <?php selected($settings['sms_provider'], 'ippanel_core'); ?>>IPPanel Core</option><option value="none" <?php selected($settings['sms_provider'], 'none'); ?>>هیچ‌کدام</option><option value="custom" <?php selected($settings['sms_provider'], 'custom'); ?>>سفارشی از طریق فیلتر wplm_sms_send</option></select></label>
        <label>حالت ارسال لایسنس<select name="sms_license_created_mode"><option value="text" <?php selected($settings['sms_license_created_mode'], 'text'); ?>>پیامک متنی</option><option value="pattern" <?php selected($settings['sms_license_created_mode'], 'pattern'); ?>>پترن</option></select></label>
    </div>
    <label>کد پترن ایجاد لایسنس<input name="sms_license_created_pattern" dir="ltr" value="<?php echo esc_attr($settings['sms_license_created_pattern']); ?>" placeholder="در حالت پترن الزامی است"></label>
    <label>قالب پیام متنی<textarea name="sms_license_created_template" rows="3"><?php echo esc_textarea($settings['sms_license_created_template']); ?></textarea></label>
    <p class="description">متغیرهای مجاز: <code>{license_key}</code> <code>{product}</code> <code>{domain}</code> <code>{expires}</code> <code>{email}</code></p>

    <h2>تنظیمات سفارشی پیامک</h2>
    <div class="wplm-two">
        <label>شماره/فرستنده<input name="sms_sender" dir="ltr" value="<?php echo esc_attr($settings['sms_sender']); ?>"></label>
        <label>API Key مستقیم، فقط برای Provider سفارشی<input name="sms_api_key" dir="ltr" type="password" value="<?php echo esc_attr($settings['sms_api_key']); ?>"></label>
    </div>

    <button class="button button-primary button-hero">ذخیره تنظیمات</button>
</form>
<?php require WPLM_PATH . 'admin/views/footer.php'; ?>
