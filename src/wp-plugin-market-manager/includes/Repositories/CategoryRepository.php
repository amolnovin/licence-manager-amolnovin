<?php
namespace WPLM\Repositories;

use WPLM\Database;

defined('ABSPATH') || exit;

final class CategoryRepository
{
    public function all(int $limit = 100, bool $active_only = false): array
    {
        global $wpdb;
        $table = Database::table('categories');
        if ($active_only) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = 'active' ORDER BY name ASC LIMIT %d", $limit));
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit));
    }

    public function find(int $id)
    {
        global $wpdb;
        $table = Database::table('categories');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function find_by_slug(string $slug)
    {
        global $wpdb;
        $table = Database::table('categories');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug));
    }

    public function save(array $data): int
    {
        global $wpdb;
        $table = Database::table('categories');
        $now = Database::now();
        $payload = [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'],
            'status' => $data['status'],
            'updated_at' => $now,
        ];
        $formats = ['%s','%s','%s','%s','%s','%s'];
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
        $wpdb->delete(Database::table('categories'), ['id' => $id], ['%d']);
    }
}
