<?php
namespace WPLM\Services;

use WPLM\Options;
use WPLM\Repositories\DiscountRepository;
use WPLM\Repositories\LicenseRepository;
use WPLM\Repositories\OrderRepository;
use WPLM\Repositories\ProductRepository;
use WPLM\Security;

defined('ABSPATH') || exit;

final class PurchaseService
{
    private const CART_COOKIE = 'wplm_cart_products';

    public static function add_to_cart(): void
    {
        Security::verify_nonce('wplm_add_to_cart');
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product = (new ProductRepository())->find($product_id);
        if (!$product || 'active' !== $product->status) {
            wp_die('محصول معتبر نیست.', '', ['response' => 404]);
        }

        $cart = self::cart_ids();
        if (!in_array($product_id, $cart, true)) {
            $cart[] = $product_id;
        }
        self::set_cart($cart);

        $cart_url = add_query_arg('cart', '1', Options::page_url('store_page_id'));
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url($cart_url));
            exit;
        }
        wp_safe_redirect($cart_url);
        exit;
    }

    public static function remove_from_cart(): void
    {
        $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'wplm_remove_cart_item')) {
            wp_die('درخواست معتبر نیست.', '', ['response' => 403]);
        }
        $cart = array_values(array_filter(self::cart_ids(), static fn($id) => (int) $id !== $product_id));
        self::set_cart($cart);
        wp_safe_redirect(add_query_arg('cart', '1', Options::page_url('store_page_id')));
        exit;
    }

    public static function start_purchase(): void
    {
        if (!Security::rate_limit('start_purchase', 10, 300)) {
            wp_die('درخواست‌های شما بیش از حد مجاز است. لطفاً چند دقیقه بعد تلاش کنید.', '', ['response' => 429]);
        }

        Security::verify_nonce('wplm_start_purchase');
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(add_query_arg('cart', '1', Options::page_url('store_page_id'))));
            exit;
        }

        $products = self::cart_products();
        if (!$products) {
            wp_die('سبد خرید شما خالی است.', '', ['response' => 422]);
        }

        $name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $mobile = isset($_POST['customer_mobile']) ? sanitize_text_field(wp_unslash($_POST['customer_mobile'])) : '';
        $domain = isset($_POST['domain']) ? Security::sanitize_domain(wp_unslash($_POST['domain'])) : '';
        $machine_id = isset($_POST['machine_id']) ? Security::sanitize_machine_id(wp_unslash($_POST['machine_id'])) : '';
        $discount_code = isset($_POST['discount_code']) ? sanitize_text_field(wp_unslash($_POST['discount_code'])) : '';

        if (!$name || !$email || !is_email($email) || !$mobile) {
            wp_die('نام، ایمیل معتبر و موبایل الزامی است.', '', ['response' => 422]);
        }

        $calculated = self::calculate_cart($products, $discount_code);
        $settings = Options::get();
        $gateway = isset($settings['default_gateway']) && 'zarinpal' === $settings['default_gateway'] ? 'zarinpal' : 'zibal';
        $final_amount = (float) $calculated['final_amount'];
        $gateway_amount = $final_amount > 0 ? ('zarinpal' === $gateway ? ZarinpalService::amount_for_gateway($final_amount) : ZibalService::amount_for_gateway($final_amount)) : 0;
        $first = reset($products);

        $order_repo = new OrderRepository();
        $order = $order_repo->create([
            'user_id' => get_current_user_id(),
            'product_id' => (int) $first->id,
            'amount' => $final_amount,
            'original_amount' => (float) $calculated['original_amount'],
            'discount_amount' => (float) $calculated['discount_amount'],
            'discount_code' => $calculated['discount_code'],
            'discount_id' => (int) $calculated['discount_id'],
            'gateway' => $gateway,
            'gateway_amount' => $gateway_amount,
            'currency' => $first->currency,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_mobile' => $mobile,
            'domain' => $domain,
            'meta' => ['cart_product_ids' => array_map(static fn($p) => (int) $p->id, $products), 'discount' => $calculated['discount_payload'], 'machine_id' => $machine_id],
        ]);

        foreach ($products as $product) {
            $item_amounts = $calculated['items'][$product->id] ?? ['price' => ProductRepository::effective_price($product), 'original_amount' => ProductRepository::effective_price($product), 'discount_amount' => 0, 'total_amount' => ProductRepository::effective_price($product)];
            $order_repo->add_item((int) $order->id, $product, $item_amounts);
        }

        if ($final_amount <= 0) {
            $licenses = self::create_licenses_for_paid_order($order);
            $first_license_id = !empty($licenses[0]->id) ? (int) $licenses[0]->id : 0;
            $order_repo->update((int) $order->id, [
                'status' => 'paid',
                'license_id' => $first_license_id,
                'paid_at' => current_time('mysql'),
                'meta' => wp_json_encode(['free_registration' => true, 'cart_product_ids' => array_map(static fn($p) => (int) $p->id, $products)]),
            ]);
            if (!empty($calculated['discount_id'])) {
                (new DiscountRepository())->increment_usage((int) $calculated['discount_id']);
            }
            self::notify_licenses($order, $licenses);
            self::clear_cart();
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'paid'));
            exit;
        }

        $callback = add_query_arg([
            'action' => 'zarinpal' === $gateway ? 'wplm_zarinpal_callback' : 'wplm_zibal_callback',
            'order_id' => (int) $order->id,
            'key' => $order->order_key,
        ], admin_url('admin-post.php'));

        $description = count($products) > 1 ? 'خرید ' . count($products) . ' محصول' : 'خرید ' . $first->name;
        $result = 'zarinpal' === $gateway
            ? ZarinpalService::request_payment($gateway_amount, $callback, $description, ['order_id' => (int) $order->id, 'mobile' => $mobile, 'email' => $email])
            : ZibalService::request_payment($gateway_amount, $callback, $description, ['order_id' => (int) $order->id, 'mobile' => $mobile]);

        if (empty($result['success'])) {
            $order_repo->update((int) $order->id, ['status' => 'failed', 'meta' => wp_json_encode(['gateway_request_error' => $result])]);
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'failed'));
            exit;
        }

        $order_repo->update((int) $order->id, ['track_id' => $result['track_id'], 'meta' => wp_json_encode(['gateway_request' => $result['raw'] ?? []])]);
        wp_redirect('zarinpal' === $gateway ? ZarinpalService::start_url($result['track_id']) : ZibalService::start_url($result['track_id']));
        exit;
    }

    public static function zibal_callback(): void
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $track_id = isset($_GET['trackId']) ? sanitize_text_field(wp_unslash($_GET['trackId'])) : '';
        $success = isset($_GET['success']) ? sanitize_text_field(wp_unslash($_GET['success'])) : '';
        $repo = new OrderRepository();
        $order = $repo->find_public($order_id, $key);
        if (!$order) {
            wp_die('سفارش معتبر نیست.', '', ['response' => 404]);
        }
        if ('paid' === $order->status) {
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'paid'));
            exit;
        }
        if ('1' !== $success || !$track_id) {
            $repo->update((int) $order->id, ['status' => 'canceled']);
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'canceled'));
            exit;
        }
        $verify = ZibalService::verify($track_id);
        if (empty($verify['success']) || (!empty($verify['amount']) && (int) $verify['amount'] !== (int) $order->gateway_amount)) {
            $repo->update((int) $order->id, ['status' => 'failed', 'track_id' => $track_id, 'meta' => wp_json_encode(['zibal_verify' => $verify])]);
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'failed'));
            exit;
        }
        self::mark_order_paid($order, ['track_id' => $track_id, 'ref_number' => $verify['ref_number'] ?? '', 'card_number' => $verify['card_number'] ?? '', 'paid_at' => !empty($verify['paid_at']) ? gmdate('Y-m-d H:i:s', strtotime($verify['paid_at'])) : current_time('mysql'), 'meta' => wp_json_encode(['zibal_verify' => $verify['raw'] ?? []])]);
    }

    public static function zarinpal_callback(): void
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $authority = isset($_GET['Authority']) ? sanitize_text_field(wp_unslash($_GET['Authority'])) : '';
        $status = isset($_GET['Status']) ? sanitize_text_field(wp_unslash($_GET['Status'])) : '';
        $repo = new OrderRepository();
        $order = $repo->find_public($order_id, $key);
        if (!$order) {
            wp_die('سفارش معتبر نیست.', '', ['response' => 404]);
        }
        if ('paid' === $order->status) {
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'paid'));
            exit;
        }
        if ('OK' !== strtoupper($status) || !$authority) {
            $repo->update((int) $order->id, ['status' => 'canceled']);
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'canceled'));
            exit;
        }
        $verify = ZarinpalService::verify($authority, (int) $order->gateway_amount);
        if (empty($verify['success'])) {
            $repo->update((int) $order->id, ['status' => 'failed', 'track_id' => $authority, 'meta' => wp_json_encode(['zarinpal_verify' => $verify])]);
            wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'failed'));
            exit;
        }
        self::mark_order_paid($order, ['track_id' => $authority, 'ref_number' => $verify['ref_number'] ?? '', 'card_number' => $verify['card_number'] ?? '', 'paid_at' => current_time('mysql'), 'meta' => wp_json_encode(['zarinpal_verify' => $verify['raw'] ?? []])]);
    }

    private static function mark_order_paid($order, array $payment_data): void
    {
        $licenses = self::create_licenses_for_paid_order($order);
        $first_license_id = !empty($licenses[0]->id) ? (int) $licenses[0]->id : 0;
        $payment_data['status'] = 'paid';
        $payment_data['license_id'] = $first_license_id;
        (new OrderRepository())->update((int) $order->id, $payment_data);
        if (!empty($order->discount_id)) {
            (new DiscountRepository())->increment_usage((int) $order->discount_id);
        }
        self::notify_licenses($order, $licenses);
        self::clear_cart();
        wp_safe_redirect(self::result_url((int) $order->id, $order->order_key, 'paid'));
        exit;
    }

    private static function cart_ids(): array
    {
        $raw = isset($_COOKIE[self::CART_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::CART_COOKIE])) : '';
        $ids = json_decode($raw, true);
        if (!is_array($ids) && isset($_COOKIE['wplm_cart_product'])) {
            $ids = [absint($_COOKIE['wplm_cart_product'])];
        }
        $ids = is_array($ids) ? array_map('absint', $ids) : [];
        return array_values(array_unique(array_filter($ids)));
    }

    public static function cart_products(): array
    {
        $repo = new ProductRepository();
        $products = [];
        foreach (self::cart_ids() as $id) {
            $product = $repo->find((int) $id);
            if ($product && 'active' === $product->status) {
                $products[(int) $product->id] = $product;
            }
        }
        return array_values($products);
    }

    private static function set_cart(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        $value = wp_json_encode($ids);
        setcookie(self::CART_COOKIE, $value, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::CART_COOKIE] = $value;
        if (!$ids) {
            setcookie('wplm_cart_product', '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            unset($_COOKIE['wplm_cart_product']);
        }
    }

    private static function clear_cart(): void
    {
        self::set_cart([]);
    }

    private static function calculate_cart(array $products, string $discount_code = ''): array
    {
        $original = 0.0;
        foreach ($products as $product) {
            // اگر محصول قیمت با تخفیف دارد، همان مبلغ پایه فروش است.
            $original += ProductRepository::effective_price($product);
        }
        $discount_total = 0.0;
        $discount_id = 0;
        $applied_code = '';
        $payload = null;
        $items = [];
        foreach ($products as $product) {
            $base_price = ProductRepository::effective_price($product);
            $items[$product->id] = ['price' => $base_price, 'original_amount' => $base_price, 'discount_amount' => 0, 'total_amount' => $base_price];
        }
        if ($discount_code) {
            $repo = new DiscountRepository();
            foreach ($products as $product) {
                $result = $repo->validate_for_product($discount_code, $product);
                if (!empty($result['valid'])) {
                    $discount_id = (int) $result['discount']->id;
                    $applied_code = (string) $result['discount']->code;
                    $amount = (float) $result['discount_amount'];
                    $discount_total += $amount;
                    $items[$product->id]['discount_amount'] = $amount;
                    $items[$product->id]['total_amount'] = max(0, $items[$product->id]['price'] - $amount);
                    $payload = ['id' => $discount_id, 'code' => $applied_code, 'amount' => $discount_total, 'type' => $result['discount']->discount_type];
                }
            }
            if (!$applied_code) {
                wp_die('کد تخفیف برای محصولات سبد خرید معتبر نیست.', '', ['response' => 422]);
            }
        }
        return ['original_amount' => $original, 'discount_amount' => $discount_total, 'final_amount' => max(0, $original - $discount_total), 'discount_id' => $discount_id, 'discount_code' => $applied_code, 'discount_payload' => $payload, 'items' => $items];
    }

    private static function create_licenses_for_paid_order($order): array
    {
        $repo = new OrderRepository();
        $items = $repo->items((int) $order->id);
        if (!$items) {
            return [];
        }
        $license_repo = new LicenseRepository();
        $order_meta = json_decode((string) ($order->meta ?? ''), true);
        $order_meta = is_array($order_meta) ? $order_meta : [];
        $machine_id = !empty($order_meta['machine_id']) ? Security::sanitize_machine_id((string) $order_meta['machine_id']) : '';
        $licenses = [];
        foreach ($items as $item) {
            if ('none' === ($item->license_mode ?? 'required')) {
                continue;
            }
            if (!empty($item->license_id)) {
                $license = $license_repo->find((int) $item->license_id);
                if ($license) {
                    $licenses[] = $license;
                }
                continue;
            }
            $request = $license_repo->create_request(['product_id' => (int) $item->product_id, 'customer_name' => $order->customer_name, 'customer_email' => $order->customer_email, 'customer_mobile' => $order->customer_mobile, 'domain' => $order->domain, 'machine_id' => $machine_id, 'source' => $order->gateway ?: 'order', 'order_id' => (int) $order->id, 'note' => 'ایجاد خودکار پس از پرداخت سفارش #' . (int) $order->id]);
            $license = $license_repo->approve_request((int) $request->id);
            if ($license) {
                $repo->update_item_license((int) $item->id, (int) $license->id);
                $licenses[] = $license;
            }
        }
        return $licenses;
    }

    private static function notify_licenses($order, array $licenses): void
    {
        foreach ($licenses as $license) {
            if (!empty($order->customer_mobile)) {
                SmsService::notify_license_created($order->customer_mobile, $license->license_key, ['license' => $license]);
            }
        }
        self::email_order($order, $licenses);
    }

    public static function result_url(int $order_id, string $key, string $status = ''): string
    {
        return add_query_arg(array_filter(['wplm_order' => $order_id, 'wplm_key' => $key, 'wplm_status' => $status]), Options::page_url('payment_result_page_id'));
    }

    private static function email_order($order, array $licenses): void
    {
        if (empty($order->customer_email) || !is_email($order->customer_email)) {
            return;
        }
        $items = (new OrderRepository())->items((int) $order->id);
        $lines = [];
        foreach ($items as $item) {
            $lines[] = '- ' . $item->product_name . ' | مبلغ: ' . number_format_i18n((float) $item->total_amount);
        }
        foreach ($licenses as $license) {
            $lines[] = 'لایسنس: ' . $license->license_key;
        }
        wp_mail($order->customer_email, 'رسید خرید و اطلاعات لایسنس', "سلام {$order->customer_name}\n\nسفارش شما با موفقیت ثبت شد.\nشماره سفارش: #{$order->id}\n\n" . implode("\n", $lines) . "\n\nبرای مشاهده خریدها وارد حساب کاربری شوید.");
    }
}
