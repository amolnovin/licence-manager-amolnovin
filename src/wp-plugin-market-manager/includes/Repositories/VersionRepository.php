<?php
namespace WPLM\Repositories;

use WPLM\Database;

defined('ABSPATH') || exit;

final class VersionRepository
{
    public function all(int $limit = 200): array
    {
        global $wpdb;
        $versions = Database::table('versions');
        $products = Database::table('products');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, p.name AS product_name, p.slug AS product_slug, p.type AS product_type FROM {$versions} v LEFT JOIN {$products} p ON p.id = v.product_id ORDER BY v.id DESC LIMIT %d",
            $limit
        ));
    }

    public function by_product(int $product_id): array
    {
        global $wpdb;
        $table = Database::table('versions');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d ORDER BY id DESC", $product_id));
    }

    public function find(int $id)
    {
        global $wpdb;
        $table = Database::table('versions');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function current_for_product(int $product_id)
    {
        global $wpdb;
        $table = Database::table('versions');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d AND is_current = 1 ORDER BY id DESC LIMIT 1", $product_id));
    }

    public function save(array $data): int
    {
        global $wpdb;
        $table = Database::table('versions');

        if (!empty($data['is_current'])) {
            $wpdb->update($table, ['is_current' => 0], ['product_id' => (int) $data['product_id']], ['%d'], ['%d']);
        }

        $payload = [
            'product_id' => (int) $data['product_id'],
            'version' => $data['version'],
            'package_url' => $data['package_url'] ?? '',
            'file_path' => $data['file_path'] ?? '',
            'file_name' => $data['file_name'] ?? '',
            'file_size' => !empty($data['file_size']) ? absint($data['file_size']) : 0,
            'changelog' => $data['changelog'] ?? '',
            'requires_wp' => $data['requires_wp'] ?? '',
            'tested_wp' => $data['tested_wp'] ?? '',
            'requires_php' => $data['requires_php'] ?? '',
            'is_current' => !empty($data['is_current']) ? 1 : 0,
        ];

        $formats = ['%d','%s','%s','%s','%s','%d','%s','%s','%s','%s','%d'];

        if (!empty($data['id'])) {
            $wpdb->update($table, $payload, ['id' => (int) $data['id']], $formats, ['%d']);
            return (int) $data['id'];
        }

        $payload['created_at'] = Database::now();
        $wpdb->insert($table, $payload, array_merge($formats, ['%s']));
        return (int) $wpdb->insert_id;
    }

    public function make_current(int $id): bool
    {
        global $wpdb;
        $version = $this->find($id);
        if (!$version) {
            return false;
        }

        $table = Database::table('versions');
        $wpdb->update($table, ['is_current' => 0], ['product_id' => (int) $version->product_id], ['%d'], ['%d']);
        $wpdb->update($table, ['is_current' => 1], ['id' => $id], ['%d'], ['%d']);

        $wpdb->update(Database::table('products'), [
            'version' => $version->version,
            'updated_at' => Database::now(),
        ], ['id' => (int) $version->product_id], ['%s','%s'], ['%d']);

        return true;
    }

    public function increment_download_count(int $id): void
    {
        global $wpdb;
        $table = Database::table('versions');
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET download_count = download_count + 1 WHERE id = %d", $id));
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(Database::table('versions'), ['id' => $id], ['%d']);
    }
}
