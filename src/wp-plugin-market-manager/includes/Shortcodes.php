<?php
namespace WPLM;

use WPLM\Repositories\CategoryRepository;
use WPLM\Repositories\OrderRepository;
use WPLM\Repositories\ProductRepository;
use WPLM\Repositories\VersionRepository;
use WPLM\Services\PackageService;
use WPLM\Services\PurchaseService;

defined('ABSPATH') || exit;

final class Shortcodes
{
    private static function front_nav(): string
    {
        $store_url = Options::page_url('store_page_id');
        $account_url = Options::page_url('account_page_id');
        $cart_url = add_query_arg('cart', '1', $store_url);
        $categories = (new CategoryRepository())->all(100, true);
        ob_start();
        ?>
        <nav class="wplm-front-nav" dir="rtl" aria-label="منوی فروشگاه آمل نوین">
            <div class="wplm-front-nav-main">
                <a class="wplm-front-nav-logo" href="<?php echo esc_url($store_url); ?>">آمل نوین</a>
                <a class="wplm-front-nav-link" href="<?php echo esc_url($store_url); ?>">خانه</a>
                <?php foreach ($categories as $cat) : ?>
                    <a class="wplm-front-nav-link" href="<?php echo esc_url(add_query_arg('category', $cat->slug, $store_url)); ?>"><?php echo esc_html($cat->name); ?></a>
                <?php endforeach; ?>
            </div>
            <div class="wplm-front-nav-actions">
                <a class="wplm-front-icon" href="<?php echo esc_url($account_url); ?>" title="حساب کاربری" aria-label="حساب کاربری">
                    <svg viewBox="0 0 24 24" width="21" height="21" aria-hidden="true"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/></svg>
                </a>
                <a class="wplm-front-icon" href="<?php echo esc_url($cart_url); ?>" title="سبد خرید" aria-label="سبد خرید">
                    <svg viewBox="0 0 24 24" width="21" height="21" aria-hidden="true"><path fill="currentColor" d="M7 18a2 2 0 1 0 2 2 2 2 0 0 0-2-2Zm10 0a2 2 0 1 0 2 2 2 2 0 0 0-2-2ZM7.2 14.5h8.9a2.5 2.5 0 0 0 2.35-1.65L21 6H6.1L5.7 4H2v2h2.05l2 9.6A2 2 0 0 0 8 17h11v-2H8Z"/></svg>
                </a>
            </div>
        </nav>
        <?php
        return (string) ob_get_clean();
    }


    public static function latest_carousel(array $atts = []): string
    {
        wp_enqueue_style('wplm-public');
        $atts = shortcode_atts([
            'limit' => 5,
            'title' => 'جدیدترین افزونه‌ها و قالب‌ها',
            'subtitle' => 'منتخب تازه‌ترین محصولات آمل نوین برای راه‌اندازی، توسعه و بهینه‌سازی سایت وردپرسی شما',
            'button' => 'مشاهده همه محصولات',
        ], $atts, 'wplm_latest_carousel');

        $limit = max(1, min(12, absint($atts['limit'])));
        $products = (new ProductRepository())->latest_active($limit);
        $store_url = Options::page_url('store_page_id');
        $carousel_id = 'wplm-carousel-' . wp_generate_password(8, false, false);

        ob_start();
        ?>
        <section class="wplm-home-carousel-section" dir="rtl">
            <div class="wplm-home-carousel-bg"></div>
            <div class="wplm-home-carousel-head">
                <div>
                    <span class="wplm-home-eyebrow">محصولات آمل نوین</span>
                    <h2><?php echo esc_html($atts['title']); ?></h2>
                    <p><?php echo esc_html($atts['subtitle']); ?></p>
                </div>
                <div class="wplm-home-carousel-actions">
                    <button type="button" class="wplm-carousel-arrow" data-wplm-carousel-prev="<?php echo esc_attr($carousel_id); ?>" aria-label="قبلی">‹</button>
                    <button type="button" class="wplm-carousel-arrow" data-wplm-carousel-next="<?php echo esc_attr($carousel_id); ?>" aria-label="بعدی">›</button>
                    <a class="wplm-btn ghost" href="<?php echo esc_url($store_url); ?>"><?php echo esc_html($atts['button']); ?></a>
                </div>
            </div>

            <div class="wplm-home-carousel-track" id="<?php echo esc_attr($carousel_id); ?>">
                <?php foreach ($products as $product) :
                    $product_url = add_query_arg('product', $product->slug, $store_url);
                    $license_label = 'none' === ($product->license_mode ?? 'required') ? 'بدون لایسنس' : 'لایسنس‌دار';
                    $type_label = 'theme' === $product->type ? 'قالب' : 'افزونه';
                ?>
                    <article class="wplm-home-product-card">
                        <a class="wplm-home-product-cover" href="<?php echo esc_url($product_url); ?>">
                            <?php if (!empty($product->cover_url)) : ?>
                                <img src="<?php echo esc_url($product->cover_url); ?>" alt="<?php echo esc_attr($product->name); ?>">
                            <?php else : ?>
                                <span class="wplm-home-placeholder">AMN</span>
                            <?php endif; ?>
                        </a>
                        <div class="wplm-home-product-body">
                            <div class="wplm-card-tags">
                                <a class="wplm-pill" href="<?php echo esc_url(add_query_arg('type', $product->type, $store_url)); ?>"><?php echo esc_html($type_label); ?></a>
                                <a class="wplm-pill secondary" href="<?php echo esc_url(add_query_arg('license', ($product->license_mode ?? 'required'), $store_url)); ?>"><?php echo esc_html($license_label); ?></a>
                            </div>
                            <h3><a href="<?php echo esc_url($product_url); ?>"><?php echo esc_html($product->name); ?></a></h3>
                            <div class="wplm-home-product-foot">
                                <a class="wplm-home-view" href="<?php echo esc_url($product_url); ?>">مشاهده محصول</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$products) : ?>
                    <p class="wplm-empty">هنوز محصول فعالی برای نمایش وجود ندارد.</p>
                <?php endif; ?>
            </div>
        </section>
        <script>
        document.addEventListener('click', function(e){
            var prev = e.target.closest('[data-wplm-carousel-prev]');
            var next = e.target.closest('[data-wplm-carousel-next]');
            var id = prev ? prev.getAttribute('data-wplm-carousel-prev') : (next ? next.getAttribute('data-wplm-carousel-next') : '');
            if(!id){ return; }
            var el = document.getElementById(id);
            if(!el){ return; }
            var step = Math.max(280, Math.floor(el.clientWidth * 0.75));
            el.scrollBy({left: next ? -step : step, behavior: 'smooth'});
        });
        </script>
        <?php
        return (string) ob_get_clean();
    }

    public static function products(array $atts = []): string
    {
        return self::store($atts);
    }

    public static function store(array $atts = []): string
    {
        wp_enqueue_style('wplm-public');
        if (!empty($_GET['cart'])) {
            return self::cart();
        }

        $slug = isset($_GET['product']) ? sanitize_title(wp_unslash($_GET['product'])) : '';
        if ($slug) {
            return self::product_detail($slug);
        }

        $type = isset($_GET['type']) && in_array($_GET['type'], ['plugin', 'theme'], true) ? sanitize_key($_GET['type']) : '';
        $category_slug = isset($_GET['category']) ? sanitize_title(wp_unslash($_GET['category'])) : '';
        $license_filter = isset($_GET['license']) && in_array($_GET['license'], ['required','none'], true) ? sanitize_key($_GET['license']) : '';
        $cat_repo = new CategoryRepository();
        $category = $category_slug ? $cat_repo->find_by_slug($category_slug) : null;
        $args = [];
        if ($type) {
            $args['type'] = $type;
        }
        if ($category) {
            $args['category_id'] = (int) $category->id;
        }
        if ($license_filter) {
            $args['license_mode'] = $license_filter;
        }

        $products = (new ProductRepository())->active($args);
        $categories = $cat_repo->all(200, true);
        $base = remove_query_arg(['product', 'category', 'type']);

        ob_start();
        ?>
        <div class="wplm-store" dir="rtl">
            <?php echo self::front_nav(); ?>
            <header class="wplm-store-hero">
                <span>فروشگاه اختصاصی</span>
                <h2>افزونه‌ها و قالب‌های حرفه‌ای</h2>
                <p>محصول موردنظر را انتخاب کنید، اطلاعات خرید را وارد کنید و پس از پرداخت زیبال، لایسنس در حساب کاربری شما فعال می‌شود.</p>
            </header>
            <nav class="wplm-filters">
                <a class="<?php echo !$type && !$category ? 'active' : ''; ?>" href="<?php echo esc_url($base); ?>">همه</a>
                <a class="<?php echo 'plugin' === $type ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('type', 'plugin', $base)); ?>">افزونه‌ها</a>
                <a class="<?php echo 'theme' === $type ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('type', 'theme', $base)); ?>">قالب‌ها</a>
                <a class="<?php echo 'required' === $license_filter ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('license', 'required', $base)); ?>">لایسنس‌دار</a>
                <a class="<?php echo 'none' === $license_filter ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('license', 'none', $base)); ?>">بدون لایسنس</a>
                <?php foreach ($categories as $cat) : ?>
                    <a class="<?php echo $category && (int)$category->id === (int)$cat->id ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('category', $cat->slug, $base)); ?>"><?php echo esc_html($cat->name); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="wplm-public-grid">
                <?php foreach ($products as $product) : ?>
                    <article class="wplm-public-card">
                        <?php if (!empty($product->cover_url)) : ?><img src="<?php echo esc_url($product->cover_url); ?>" alt="<?php echo esc_attr($product->name); ?>"><?php endif; ?>
                        <div class="wplm-card-body">
                            <div class="wplm-card-tags"><a class="wplm-pill" href="<?php echo esc_url(add_query_arg('type', $product->type, $base)); ?>"><?php echo 'theme' === $product->type ? 'قالب' : 'افزونه'; ?></a><a class="wplm-pill secondary" href="<?php echo esc_url(add_query_arg('license', ($product->license_mode ?? 'required'), $base)); ?>"><?php echo 'none' === ($product->license_mode ?? 'required') ? 'بدون لایسنس' : 'لایسنس‌دار'; ?></a></div>
                            <h3><?php echo esc_html($product->name); ?></h3>
                            <p><?php echo esc_html(wp_trim_words($product->short_description ?: wp_strip_all_tags(do_shortcode((string) $product->description)), 45, '...')); ?></p>
                            <div class="wplm-card-footer">
                                <a class="wplm-btn" href="<?php echo esc_url(add_query_arg('product', $product->slug, $base)); ?>">مشاهده و خرید</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$products) : ?><p class="wplm-empty">محصولی برای نمایش وجود ندارد.</p><?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function product_detail(string $slug): string
    {
        $product = (new ProductRepository())->find_by_slug($slug);
        if (!$product || 'active' !== $product->status || empty($product->is_visible)) {
            return '<div class="wplm-store" dir="rtl"><p class="wplm-empty">محصول پیدا نشد.</p></div>';
        }

        $settings = Options::get();
        $gateway_label = !empty($settings['default_gateway']) && 'zarinpal' === $settings['default_gateway'] ? 'زرین‌پال' : 'زیبال';
        $already_bought = is_user_logged_in() ? (new OrderRepository())->user_bought_product(get_current_user_id(), (int) $product->id) : false;
        ob_start();
        ?>
        <div class="wplm-store wplm-single wplm-modern-single" dir="rtl">
            <?php echo self::front_nav(); ?>
            <a class="wplm-back" href="<?php echo esc_url(remove_query_arg(['product','cart'])); ?>">← بازگشت به فروشگاه</a>
            <section class="wplm-product-hero">
                <div class="wplm-product-hero-content">
                    <span class="wplm-pill"><?php echo 'theme' === $product->type ? 'قالب حرفه‌ای وردپرس' : 'افزونه حرفه‌ای وردپرس'; ?></span>
                    <h1><?php echo esc_html($product->name); ?></h1>
                    <p><?php echo esc_html($product->short_description ?: wp_trim_words(wp_strip_all_tags((string)$product->description), 28)); ?></p>
                    <div class="wplm-product-meta">
                        <span>نسخه <b dir="ltr"><?php echo esc_html($product->version); ?></b></span>
                        <span>تحویل فوری لایسنس</span>
                        <span>آپدیت امن از پیشخوان</span>
                    </div>
                </div>
                <div class="wplm-product-hero-media">
                    <?php if (!empty($product->cover_url)) : ?><img src="<?php echo esc_url($product->cover_url); ?>" alt="<?php echo esc_attr($product->name); ?>"><?php else : ?><div class="wplm-cover-placeholder">AMN</div><?php endif; ?>
                </div>
            </section>
            <div class="wplm-single-grid">
                <article class="wplm-single-content">
                    <h2>معرفی محصول</h2>
                    <div class="wplm-description"><?php echo wp_kses_post(do_shortcode(wpautop((string) $product->description))); ?></div>
                    <div class="wplm-feature-grid">
                        <div><strong><?php echo 'none' === ($product->license_mode ?? 'required') ? 'بدون نیاز به لایسنس' : 'لایسنس آنلاین'; ?></strong><span><?php echo 'none' === ($product->license_mode ?? 'required') ? 'دانلود و استفاده بدون قفل لایسنس' : 'فعال‌سازی روی دامنه مجاز'; ?></span></div>
                        <div><strong>بروزرسانی خودکار</strong><span>نمایش آپدیت در بخش افزونه‌های وردپرس</span></div>
                        <div><strong>دانلود امن</strong><span>دریافت فایل از حساب کاربری</span></div>
                    </div>
                </article>
                <aside class="wplm-checkout-card wplm-sticky-buy">
                    <h3>خرید محصول</h3>
                    <div class="wplm-price"><?php echo esc_html(number_format_i18n((float) $product->price)); ?> <span><?php echo esc_html($product->currency); ?></span></div>
                    <ul>
                        <li>درگاه پیش‌فرض: <b><?php echo esc_html($gateway_label); ?></b></li>
                        <li>وضعیت: <b><?php echo $already_bought ? 'قبلاً خریداری شده' : 'آماده خرید'; ?></b></li>
                        <li>صدور لایسنس: <b><?php echo 'none' === ($product->license_mode ?? 'required') ? 'نیاز ندارد' : 'خودکار'; ?></b></li>
                        <li>حساب کاربری: <b>الزامی برای پرداخت</b></li>
                    </ul>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="wplm_add_to_cart">
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($product->id); ?>">
                        <?php wp_nonce_field('wplm_add_to_cart'); ?>
                        <?php if ($already_bought) : ?><div class="wplm-disabled-note">شما قبلاً این محصول را خریداری کرده‌اید. در صورت نیاز می‌توانید خرید مجدد انجام دهید.</div><?php endif; ?>
                        <button class="wplm-btn wplm-btn-block" type="submit"><?php echo $already_bought ? 'خرید مجدد / افزودن به سبد' : 'افزودن به سبد خرید'; ?></button>
                    </form>
                    <a class="wplm-account-link" href="<?php echo esc_url(Options::page_url('account_page_id')); ?>">مشاهده خریدهای من</a>
                </aside>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function cart(): string
    {
        wp_enqueue_style('wplm-public');
        $products = PurchaseService::cart_products();
        if (!$products) {
            return '<div class="wplm-store" dir="rtl">' . self::front_nav() . '<p class="wplm-empty">سبد خرید شما خالی است.</p><a class="wplm-btn" href="' . esc_url(Options::page_url('store_page_id')) . '">بازگشت به فروشگاه</a></div>';
        }
        if (!is_user_logged_in()) {
            $cart_url = add_query_arg('cart', '1', Options::page_url('store_page_id'));
            return '<div class="wplm-store" dir="rtl">' . self::front_nav() . '<div class="wplm-auth-box"><h2>ورود برای ادامه خرید</h2><p>برای مشاهده سفارش و پرداخت آنلاین ابتدا وارد حساب کاربری شوید.</p><a class="wplm-btn" href="' . esc_url(wp_login_url($cart_url)) . '">ورود به وردپرس</a></div></div>';
        }
        $user = wp_get_current_user();
        $subtotal = array_sum(array_map(static fn($p) => (float) $p->price, $products));
        ob_start();
        ?>
        <div class="wplm-store wplm-cart" dir="rtl">
            <?php echo self::front_nav(); ?>
            <header class="wplm-store-hero"><span>سبد خرید</span><h2>مشاهده سفارش و پرداخت آنلاین</h2><p>محصولات اضافه‌شده به سبد خرید را بررسی کنید و سپس پرداخت را انجام دهید.</p></header>
            <div class="wplm-cart-layout">
                <article class="wplm-single-content wplm-order-review">
                    <h3>محصولات سبد خرید</h3>
                    <div class="wplm-cart-table-wrap">
                        <table class="wplm-cart-table">
                            <thead><tr><th>محصول</th><th>نوع</th><th>لایسنس</th><th>قیمت</th><th>حذف</th></tr></thead>
                            <tbody>
                            <?php foreach ($products as $product) : ?>
                                <tr>
                                    <td class="wplm-cart-product">
                                        <?php if (!empty($product->cover_url)) : ?><img src="<?php echo esc_url($product->cover_url); ?>" alt=""><?php endif; ?>
                                        <div><strong><?php echo esc_html($product->name); ?></strong><small><?php echo esc_html($product->short_description); ?></small></div>
                                    </td>
                                    <td><?php echo 'theme' === $product->type ? 'قالب' : 'افزونه'; ?></td>
                                    <td><span class="wplm-pill secondary"><?php echo 'none' === ($product->license_mode ?? 'required') ? 'بدون لایسنس' : 'لایسنس‌دار'; ?></span></td>
                                    <td><strong><?php echo esc_html(number_format_i18n((float) $product->price)); ?></strong> <?php echo esc_html($product->currency); ?></td>
                                    <td><a class="wplm-remove" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'wplm_remove_from_cart','product_id'=>(int)$product->id], admin_url('admin-post.php')), 'wplm_remove_cart_item')); ?>">×</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a class="wplm-btn ghost" href="<?php echo esc_url(Options::page_url('store_page_id')); ?>">ادامه خرید</a>
                </article>
                <aside class="wplm-checkout-card">
                    <h3>اطلاعات خریدار</h3>
                    <div class="wplm-cart-total"><span>مجموع سبد خرید</span><strong><?php echo esc_html(number_format_i18n($subtotal)); ?></strong></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wplm-buy-form">
                        <input type="hidden" name="action" value="wplm_start_purchase">
                        <?php wp_nonce_field('wplm_start_purchase'); ?>
                        <label>نام و نام خانوادگی<input name="customer_name" required value="<?php echo esc_attr($user->display_name); ?>"></label>
                        <label>ایمیل<input name="customer_email" type="email" required dir="ltr" value="<?php echo esc_attr($user->user_email); ?>"></label>
                        <label>موبایل<input name="customer_mobile" required dir="ltr" placeholder="09123456789"></label>
                        <label>دامنه برای فعال‌سازی اولیه<input name="domain" dir="ltr" placeholder="example.com"></label>
                        <label>کد تخفیف، اختیاری<input name="discount_code" dir="ltr" placeholder="مثلاً SUMMER30"></label>
                        <button class="wplm-btn wplm-btn-block" type="submit"><?php echo $subtotal > 0 ? 'پرداخت آنلاین' : 'ثبت رایگان و دریافت'; ?></button>
                    </form>
                </aside>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function account(array $atts = []): string
    {
        wp_enqueue_style('wplm-public');
        ob_start();
        $account_url = Options::page_url('account_page_id');
        $tabs = [
            'dashboard' => 'پیشخوان',
            'orders' => 'سفارشات',
            'purchases' => 'خریدها',
            'settings' => 'تنظیمات',
        ];
        $tab = isset($_GET['wplm_tab']) ? sanitize_key(wp_unslash($_GET['wplm_tab'])) : 'dashboard';
        if (!isset($tabs[$tab])) {
            $tab = 'dashboard';
        }
        ?>
        <div class="wplm-store wplm-account wplm-account-pro" dir="rtl">
            <?php echo self::front_nav(); ?>
            <header class="wplm-store-hero wplm-account-hero">
                <span>حساب کاربری آمل نوین</span>
                <h2>مدیریت سفارش‌ها، خریدها و دانلودها</h2>
                <p>در این بخش می‌توانید سفارش‌ها، محصولات خریداری‌شده، لایسنس‌ها، دانلودها و تنظیمات حساب خود را مدیریت کنید.</p>
            </header>
            <?php if (!is_user_logged_in()) : ?>
                <div class="wplm-auth-box">
                    <p>برای مشاهده حساب کاربری وارد شوید.</p>
                    <?php wp_login_form(['redirect' => $account_url]); ?>
                </div>
            <?php else :
                $user = wp_get_current_user();
                $order_repo = new OrderRepository();
                $orders = $order_repo->by_user(get_current_user_id());
                $version_repo = new VersionRepository();
                $paid_count = 0;
                $total_spent = 0;
                foreach ($orders as $order) {
                    if ('paid' === $order->status) {
                        $paid_count++;
                        $total_spent += (float) $order->amount;
                    }
                }
            ?>
                <div class="wplm-account-layout">
                    <aside class="wplm-account-sidebar">
                        <div class="wplm-account-user">
                            <div class="wplm-account-avatar"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($user->display_name ?: $user->user_login, 0, 1) : substr($user->display_name ?: $user->user_login, 0, 1)); ?></div>
                            <strong><?php echo esc_html($user->display_name ?: $user->user_login); ?></strong>
                            <small dir="ltr"><?php echo esc_html($user->user_email); ?></small>
                        </div>
                        <nav class="wplm-account-nav">
                            <?php foreach ($tabs as $key => $label) : ?>
                                <a class="<?php echo $tab === $key ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('wplm_tab', $key, $account_url)); ?>"><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                            <a class="logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">خروج</a>
                        </nav>
                    </aside>
                    <main class="wplm-account-content">
                        <?php if (!empty($_GET['wplm_saved'])) : ?><div class="wplm-alert success">تنظیمات با موفقیت ذخیره شد.</div><?php endif; ?>
                        <?php if (!empty($_GET['wplm_error'])) : ?><div class="wplm-alert error"><?php echo esc_html(wp_unslash($_GET['wplm_error'])); ?></div><?php endif; ?>

                        <?php if ('dashboard' === $tab) : ?>
                            <div class="wplm-account-stats">
                                <div><span>کل سفارش‌ها</span><strong><?php echo esc_html(count($orders)); ?></strong></div>
                                <div><span>سفارش موفق</span><strong><?php echo esc_html($paid_count); ?></strong></div>
                                <div><span>مجموع خرید</span><strong><?php echo esc_html(number_format_i18n($total_spent)); ?></strong></div>
                            </div>
                            <section class="wplm-account-section"><h3>آخرین سفارش‌ها</h3><?php echo self::account_orders_table(array_slice($orders, 0, 5)); ?></section>
                        <?php elseif ('orders' === $tab) : ?>
                            <section class="wplm-account-section"><h3>سفارشات من</h3><?php echo self::account_orders_table($orders); ?></section>
                        <?php elseif ('purchases' === $tab) : ?>
                            <section class="wplm-account-section"><h3>خریدهای من</h3>
                                <div class="wplm-purchase-grid">
                                    <?php foreach ($orders as $order) : if ('paid' !== $order->status) { continue; } $items = $order_repo->items((int) $order->id); foreach ($items as $item) :
                                        $current_version = $version_repo->current_for_product((int) $item->product_id);
                                        $license_required = 'none' !== ($item->license_mode ?? 'required');
                                        $license_not_expired = empty($item->expires_at) || strtotime($item->expires_at) >= time();
                                        $can_download = $current_version && (!$license_required || ('active' === $item->license_status && $license_not_expired && !empty($item->license_id)));
                                        $download_url = $can_download ? PackageService::account_download_url((int) $current_version->id, (int) ($item->license_id ?? 0), get_current_user_id(), (int) $order->id) : '';
                                        $modal_id = 'wplm-purchase-modal-' . (int) $order->id . '-' . (int) $item->id;
                                    ?>
                                        <article class="wplm-purchase-card">
                                            <?php if (!empty($item->cover_url)) : ?><img src="<?php echo esc_url($item->cover_url); ?>" alt=""><?php endif; ?>
                                            <div><span class="wplm-pill"><?php echo 'theme' === $item->product_type ? 'قالب' : 'افزونه'; ?></span><h4><?php echo esc_html($item->product_name); ?></h4><small>سفارش #<?php echo esc_html((int) $order->id); ?></small></div>
                                            <div class="wplm-purchase-actions">
                                                <button type="button" class="wplm-btn ghost" data-wplm-open-purchase="<?php echo esc_attr($modal_id); ?>">جزئیات</button>
                                                <?php if ($download_url) : ?><a class="wplm-btn" href="<?php echo esc_url($download_url); ?>">دانلود</a><?php endif; ?>
                                            </div>
                                        </article>
                                        <div class="wplm-purchase-modal" id="<?php echo esc_attr($modal_id); ?>" aria-hidden="true">
                                            <div class="wplm-purchase-modal-bg" data-wplm-close-purchase></div>
                                            <div class="wplm-purchase-modal-box">
                                                <button type="button" class="wplm-purchase-close" data-wplm-close-purchase>×</button>
                                                <h3><?php echo esc_html($item->product_name); ?></h3>
                                                <div class="wplm-account-meta">
                                                    <span>سفارش: <b>#<?php echo esc_html((int)$order->id); ?></b></span>
                                                    <span>مبلغ: <b><?php echo esc_html(number_format_i18n((float)$item->total_amount)); ?></b></span>
                                                    <span>نوع لایسنس: <b><?php echo $license_required ? 'لایسنس‌دار' : 'بدون لایسنس'; ?></b></span>
                                                    <span>نسخه: <b dir="ltr"><?php echo esc_html($current_version->version ?? $item->product_version ?? '—'); ?></b></span>
                                                </div>
                                                <?php if (!empty($item->license_key)) : ?><div class="wplm-license-row"><code class="wplm-license" dir="ltr"><?php echo esc_html($item->license_key); ?></code><button type="button" class="wplm-copy-btn" data-copy="<?php echo esc_attr($item->license_key); ?>">کپی کلید</button></div><?php endif; ?>
                                                <?php if ($current_version && !empty($current_version->changelog)) : ?><details class="wplm-changelog" open><summary>تغییرات نسخه</summary><?php echo wp_kses_post(wpautop((string)$current_version->changelog)); ?></details><?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; endforeach; ?>
                                </div>
                                <?php if (!$paid_count) : ?><p class="wplm-empty">هنوز خرید موفقی ثبت نشده است.</p><?php endif; ?>
                            </section>
                        <?php elseif ('settings' === $tab) : ?>
                            <section class="wplm-account-section"><h3>تنظیمات حساب</h3>
                                <form class="wplm-buy-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="wplm_account_update_password">
                                    <?php wp_nonce_field('wplm_account_update_password'); ?>
                                    <label>رمز عبور فعلی<input type="password" name="current_password" required></label>
                                    <label>رمز عبور جدید<input type="password" name="new_password" minlength="8" required></label>
                                    <label>تکرار رمز عبور جدید<input type="password" name="confirm_password" minlength="8" required></label>
                                    <button class="wplm-btn" type="submit">تغییر رمز عبور</button>
                                </form>
                            </section>
                        <?php endif; ?>
                    </main>
                </div>
                <script>
                document.addEventListener('click',function(e){var o=e.target.closest('[data-wplm-open-purchase]');if(o){var m=document.getElementById(o.getAttribute('data-wplm-open-purchase'));if(m)m.classList.add('is-open');}if(e.target.closest('[data-wplm-close-purchase]')){e.target.closest('.wplm-purchase-modal').classList.remove('is-open');}var btn=e.target.closest('.wplm-copy-btn');if(btn){var text=btn.getAttribute('data-copy')||'';if(navigator.clipboard){navigator.clipboard.writeText(text);}btn.textContent='کپی شد';setTimeout(function(){btn.textContent='کپی کلید';},1600);}});
                </script>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function account_orders_table(array $orders): string
    {
        ob_start();
        ?>
        <div class="wplm-account-table-wrap"><table class="wplm-account-table"><thead><tr><th>شماره</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th></tr></thead><tbody>
        <?php foreach ($orders as $order) : ?><tr><td>#<?php echo esc_html((int)$order->id); ?></td><td><?php echo esc_html(mysql2date('Y/m/d', $order->created_at)); ?></td><td><?php echo esc_html(number_format_i18n((float)$order->amount)); ?> <?php echo esc_html($order->currency); ?></td><td><span class="wplm-status <?php echo esc_attr($order->status); ?>"><?php echo esc_html(['pending'=>'در انتظار','paid'=>'پرداخت‌شده','failed'=>'ناموفق','canceled'=>'لغو شده'][$order->status] ?? $order->status); ?></span></td></tr><?php endforeach; ?>
        <?php if (!$orders) : ?><tr><td colspan="4">سفارشی وجود ندارد.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
        return (string) ob_get_clean();
    }

    public static function payment_result(array $atts = []): string
    {
        wp_enqueue_style('wplm-public');
        $order_id = isset($_GET['wplm_order']) ? absint($_GET['wplm_order']) : 0;
        $key = isset($_GET['wplm_key']) ? sanitize_text_field(wp_unslash($_GET['wplm_key'])) : '';
        $order = $order_id && $key ? (new OrderRepository())->find_public($order_id, $key) : null;
        ob_start();
        ?>
        <div class="wplm-store wplm-result" dir="rtl">
            <?php echo self::front_nav(); ?>
            <?php if (!$order) : ?>
                <div class="wplm-result-box failed"><h2>سفارش پیدا نشد</h2><p>اطلاعات نتیجه خرید معتبر نیست.</p></div>
            <?php else : ?>
                <div class="wplm-result-box <?php echo esc_attr($order->status); ?>">
                    <h2><?php echo 'paid' === $order->status ? 'پرداخت موفق بود' : 'پرداخت تکمیل نشد'; ?></h2>
                    <p>شماره سفارش: <b>#<?php echo esc_html((int)$order->id); ?></b></p>
                    <p>وضعیت: <b><?php echo esc_html(['pending'=>'در انتظار','paid'=>'پرداخت‌شده','failed'=>'ناموفق','canceled'=>'لغو شده'][$order->status] ?? $order->status); ?></b></p>
                    <?php if (!empty($order->ref_number)) : ?><p>کد پیگیری: <code dir="ltr"><?php echo esc_html($order->ref_number); ?></code></p><?php endif; ?>
                    <div class="wplm-result-actions">
                        <a class="wplm-btn" href="<?php echo esc_url(Options::page_url('account_page_id')); ?>">مشاهده حساب کاربری</a>
                        <a class="wplm-btn ghost" href="<?php echo esc_url(Options::page_url('store_page_id')); ?>">بازگشت به فروشگاه</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
