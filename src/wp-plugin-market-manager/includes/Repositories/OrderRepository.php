<?php
namespace WPLM\Repositories;

use WPLM\Database;
use WPLM\Security;

defined('ABSPATH') || exit;

final class OrderRepository
{
    public function create(array $data)
    {
        global $wpdb;
        $now = Database::now();
        $key = wp_generate_password(32, false, false);
        $wpdb->insert(Database::table('orders'), [
            'user_id' => !empty($data['user_id']) ? absint($data['user_id']) : null,
            'product_id' => absint($data['product_id']),
            'license_id' => null,
            'order_key' => $key,
            'status' => 'pending',
            'amount' => (float) ($data['amount'] ?? 0),
            'original_amount' => (float) ($data['original_amount'] ?? $data['amount'] ?? 0),
            'discount_amount' => (float) ($data['discount_amount'] ?? 0),
            'discount_code' => !empty($data['discount_code']) ? sanitize_text_field((string) $data['discount_code']) : null,
            'discount_id' => !empty($data['discount_id']) ? absint($data['discount_id']) : null,
            'gateway_amount' => absint($data['gateway_amount']),
            'currency' => $data['currency'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_mobile' => $data['customer_mobile'],
            'domain' => Security::sanitize_domain((string) $data['domain']),
            'gateway' => !empty($data['gateway']) ? sanitize_key($data['gateway']) : 'zibal',
            'track_id' => '',
            'ref_number' => '',
            'card_number' => '',
            'paid_at' => null,
            'meta' => wp_json_encode($data['meta'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%d','%d','%s','%s','%f','%f','%f','%s','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);

        return $this->find((int) $wpdb->insert_id);
    }

    public function add_item(int $order_id, $product, array $amounts = []): int
    {
        global $wpdb;
        $now = Database::now();
        $price = (float) ($amounts['price'] ?? $product->price ?? 0);
        $original = (float) ($amounts['original_amount'] ?? $price);
        $discount = (float) ($amounts['discount_amount'] ?? 0);
        $total = (float) ($amounts['total_amount'] ?? max(0, $original - $discount));
        $wpdb->insert(Database::table('order_items'), [
            'order_id' => $order_id,
            'product_id' => (int) $product->id,
            'license_id' => null,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'product_type' => $product->type,
            'license_mode' => $product->license_mode ?? 'required',
            'qty' => 1,
            'price' => $price,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%d','%d','%s','%s','%s','%s','%d','%f','%f','%f','%f','%s','%s']);
        return (int) $wpdb->insert_id;
    }

    public function items(int $order_id): array
    {
        global $wpdb;
        $items = Database::table('order_items');
        $products = Database::table('products');
        $licenses = Database::table('licenses');
        return $wpdb->get_results($wpdb->prepare("SELECT i.*, p.cover_url, p.short_description, p.version AS product_version, l.license_key, l.expires_at, l.status AS license_status, l.allowed_domains, l.domains FROM {$items} i LEFT JOIN {$products} p ON p.id = i.product_id LEFT JOIN {$licenses} l ON l.id = i.license_id WHERE i.order_id = %d ORDER BY i.id ASC", $order_id));
    }

    public function update_item_license(int $item_id, int $license_id): void
    {
        global $wpdb;
        $wpdb->update(Database::table('order_items'), ['license_id' => $license_id, 'updated_at' => Database::now()], ['id' => $item_id], ['%d','%s'], ['%d']);
    }

    public function find(int $id)
    {
        global $wpdb;
        $table = Database::table('orders');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function find_public(int $id, string $key)
    {
        global $wpdb;
        $table = Database::table('orders');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND order_key = %s", $id, $key));
    }

    public function find_by_track_id(string $track_id)
    {
        global $wpdb;
        $table = Database::table('orders');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE track_id = %s", $track_id));
    }

    public function update(int $id, array $data): void
    {
        global $wpdb;
        $data['updated_at'] = Database::now();
        $formats = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['user_id', 'product_id', 'license_id', 'gateway_amount', 'discount_id'], true)) {
                $formats[] = '%d';
            } elseif (in_array($key, ['amount', 'original_amount', 'discount_amount'], true)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        $wpdb->update(Database::table('orders'), $data, ['id' => $id], $formats, ['%d']);
    }

    public function all(int $limit = 100): array
    {
        global $wpdb;
        $orders = Database::table('orders');
        $products = Database::table('products');
        return $wpdb->get_results($wpdb->prepare("SELECT o.*, p.name AS product_name, p.slug AS product_slug FROM {$orders} o LEFT JOIN {$products} p ON p.id = o.product_id ORDER BY o.id DESC LIMIT %d", $limit));
    }

    public function by_user(int $user_id): array
    {
        global $wpdb;
        $orders = Database::table('orders');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$orders} WHERE user_id = %d ORDER BY id DESC", $user_id));
    }

    public function user_owns_license(int $user_id, int $license_id): bool
    {
        global $wpdb;
        $orders = Database::table('orders');
        $items = Database::table('order_items');
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT o.id FROM {$orders} o INNER JOIN {$items} i ON i.order_id = o.id WHERE o.user_id = %d AND i.license_id = %d AND o.status = 'paid' LIMIT 1", $user_id, $license_id));
    }

    public function user_owns_order(int $user_id, int $order_id, int $product_id = 0): bool
    {
        global $wpdb;
        $orders = Database::table('orders');
        $items = Database::table('order_items');
        if ($product_id) {
            return (bool) $wpdb->get_var($wpdb->prepare("SELECT o.id FROM {$orders} o INNER JOIN {$items} i ON i.order_id = o.id WHERE o.id = %d AND o.user_id = %d AND i.product_id = %d AND o.status = 'paid' LIMIT 1", $order_id, $user_id, $product_id));
        }
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$orders} WHERE id = %d AND user_id = %d AND status = 'paid' LIMIT 1", $order_id, $user_id));
    }

    public function user_bought_product(int $user_id, int $product_id): bool
    {
        global $wpdb;
        $orders = Database::table('orders');
        $items = Database::table('order_items');
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT o.id FROM {$orders} o INNER JOIN {$items} i ON i.order_id = o.id WHERE o.user_id = %d AND i.product_id = %d AND o.status = 'paid' LIMIT 1", $user_id, $product_id));
    }

    public function stats(): array
    {
        global $wpdb;
        $orders = Database::table('orders');
        return [
            'today_revenue' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$orders} WHERE status = 'paid' AND DATE(paid_at) = CURDATE()"),
            'month_revenue' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$orders} WHERE status = 'paid' AND YEAR(paid_at) = YEAR(CURDATE()) AND MONTH(paid_at) = MONTH(CURDATE())"),
            'total_revenue' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$orders} WHERE status = 'paid'"),
            'discount_total' => (float) $wpdb->get_var("SELECT COALESCE(SUM(discount_amount),0) FROM {$orders} WHERE status = 'paid'"),
        ];
    }
}
