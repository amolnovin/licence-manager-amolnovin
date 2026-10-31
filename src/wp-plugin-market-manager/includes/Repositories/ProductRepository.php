<?php
namespace WPLM\Repositories;

use WPLM\Database;

defined('ABSPATH') || exit;

final class ProductRepository
{
    public function all(int $limit = 100): array
    {
        global $wpdb;
        $table = Database::table('products');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit));
    }

    public function active(array $args = []): array
    {
        global $wpdb;
        $table = Database::table('products');
        $where = ["status = 'active'"];
        $params = [];

        if (empty($args['include_hidden'])) {
            $where[] = 'is_visible = 1';
        }

        if (!empty($args['type']) && in_array($args['type'], ['plugin', 'theme', 'windows'], true)) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }
        if (!empty($args['category_id'])) {
            $where[] = 'category_id = %d';
            $params[] = absint($args['category_id']);
        }
        if (isset($args['license_mode']) && in_array($args['license_mode'], ['required', 'none'], true)) {
            $where[] = 'license_mode = %s';
            $params[] = $args['license_mode'];
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC';
        if ($params) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        }
        return $wpdb->get_results($sql);
    }


    public function latest_active(int $limit = 5): array
    {
        global $wpdb;
        $table = Database::table('products');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = 'active' AND is_visible = 1 ORDER BY id DESC LIMIT %d", max(1, $limit)));
    }

    public function find(int $id)
    {
        global $wpdb;
        $table = Database::table('products');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function find_by_slug(string $slug)
    {
        global $wpdb;
        $table = Database::table('products');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug));
    }

    public function save(array $data): int
    {
        global $wpdb;
        $table = Database::table('products');
        $now = Database::now();

        $payload = [
            'category_id' => !empty($data['category_id']) ? absint($data['category_id']) : null,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'type' => $data['type'],
            'cover_url' => $data['cover_url'] ?? '',
            'short_description' => $data['short_description'] ?? '',
            'description' => $data['description'],
            'version' => $data['version'],
            'price' => $data['price'],
            'currency' => $data['currency'],
            'license_mode' => isset($data['license_mode']) && 'none' === $data['license_mode'] ? 'none' : 'required',
            'is_visible' => isset($data['is_visible']) ? absint($data['is_visible']) : 1,
            'status' => $data['status'],
            'updated_at' => $now,
        ];

        $formats = ['%d','%s','%s','%s','%s','%s','%s','%s','%f','%s','%s','%d','%s','%s'];

        if (!empty($data['id'])) {
            $wpdb->update($table, $payload, ['id' => (int) $data['id']], $formats, ['%d']);
            return (int) $data['id'];
        }

        $payload['created_at'] = $now;
        $wpdb->insert($table, $payload, array_merge($formats, ['%s']));
        return (int) $wpdb->insert_id;
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(Database::table('products'), ['id' => $id], ['%d']);
    }

    public function counts(): array
    {
        global $wpdb;
        $products = Database::table('products');
        $licenses = Database::table('licenses');
        $requests = Database::table('license_requests');
        $orders = Database::table('orders');
        return [
            'products' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$products}"),
            'active_products' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$products} WHERE status = 'active'"),
            'licenses' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$licenses}"),
            'pending_requests' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$requests} WHERE status = 'pending'"),
            'orders' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$orders}"),
            'paid_orders' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$orders} WHERE status = 'paid'"),
        ];
    }
}
