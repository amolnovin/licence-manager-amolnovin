<?php
namespace WPLM\Repositories;

use WPLM\Database;

defined('ABSPATH') || exit;

final class DiscountRepository
{
    public function all(int $limit = 200): array
    {
        global $wpdb;
        $discounts = Database::table('discounts');
        $products = Database::table('products');
        $categories = Database::table('categories');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, p.name AS product_name, c.name AS category_name FROM {$discounts} d LEFT JOIN {$products} p ON p.id = d.product_id LEFT JOIN {$categories} c ON c.id = d.category_id ORDER BY d.id DESC LIMIT %d",
            $limit
        ));
    }

    public function find(int $id)
    {
        global $wpdb;
        $table = Database::table('discounts');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function find_by_code(string $code)
    {
        global $wpdb;
        $table = Database::table('discounts');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s", $this->normalize_code($code)));
    }

    public function save(array $data): int
    {
        global $wpdb;
        $table = Database::table('discounts');
        $now = Database::now();
        $payload = [
            'code' => $this->normalize_code($data['code'] ?? ''),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'discount_type' => in_array(($data['discount_type'] ?? 'percent'), ['percent', 'fixed'], true) ? $data['discount_type'] : 'percent',
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'product_id' => !empty($data['product_id']) ? absint($data['product_id']) : null,
            'category_id' => !empty($data['category_id']) ? absint($data['category_id']) : null,
            'starts_at' => !empty($data['starts_at']) ? sanitize_text_field($data['starts_at']) : null,
            'ends_at' => !empty($data['ends_at']) ? sanitize_text_field($data['ends_at']) : null,
            'usage_limit' => !empty($data['usage_limit']) ? absint($data['usage_limit']) : 0,
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'draft'], true) ? $data['status'] : 'active',
            'updated_at' => $now,
        ];

        $formats = ['%s','%s','%s','%f','%d','%d','%s','%s','%d','%s','%s'];
        if (!empty($data['id'])) {
            $wpdb->update($table, $payload, ['id' => (int) $data['id']], $formats, ['%d']);
            return (int) $data['id'];
        }

        $payload['used_count'] = 0;
        $payload['created_at'] = $now;
        $wpdb->insert($table, $payload, array_merge($formats, ['%d','%s']));
        return (int) $wpdb->insert_id;
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(Database::table('discounts'), ['id' => $id], ['%d']);
    }

    public function increment_usage(int $id): void
    {
        global $wpdb;
        $table = Database::table('discounts');
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET used_count = used_count + 1, updated_at = %s WHERE id = %d", Database::now(), $id));
    }

    public function validate_for_product(string $code, $product): array
    {
        $code = $this->normalize_code($code);
        if (!$code) {
            return ['valid' => false, 'code' => 'empty'];
        }

        $discount = $this->find_by_code($code);
        if (!$discount || 'active' !== $discount->status) {
            return ['valid' => false, 'code' => 'not_found', 'message' => 'کد تخفیف معتبر نیست.'];
        }

        $now = current_time('timestamp');
        if (!empty($discount->starts_at) && strtotime($discount->starts_at) > $now) {
            return ['valid' => false, 'code' => 'not_started', 'message' => 'زمان استفاده از این کد تخفیف هنوز شروع نشده است.'];
        }
        if (!empty($discount->ends_at) && strtotime($discount->ends_at) < $now) {
            return ['valid' => false, 'code' => 'expired', 'message' => 'مهلت استفاده از این کد تخفیف پایان یافته است.'];
        }
        if (!empty($discount->usage_limit) && (int) $discount->used_count >= (int) $discount->usage_limit) {
            return ['valid' => false, 'code' => 'usage_limit', 'message' => 'ظرفیت استفاده از این کد تخفیف تکمیل شده است.'];
        }
        if (!empty($discount->product_id) && (int) $discount->product_id !== (int) $product->id) {
            return ['valid' => false, 'code' => 'product_mismatch', 'message' => 'این کد تخفیف برای این محصول قابل استفاده نیست.'];
        }
        if (!empty($discount->category_id) && (int) $discount->category_id !== (int) $product->category_id) {
            return ['valid' => false, 'code' => 'category_mismatch', 'message' => 'این کد تخفیف برای دسته‌بندی این محصول قابل استفاده نیست.'];
        }

        $discount_amount = $this->calculate_amount($discount, (float) $product->price);
        if ($discount_amount <= 0) {
            return ['valid' => false, 'code' => 'zero', 'message' => 'مقدار تخفیف قابل اعمال نیست.'];
        }

        return [
            'valid' => true,
            'discount' => $discount,
            'discount_amount' => $discount_amount,
            'final_amount' => max(0, (float) $product->price - $discount_amount),
        ];
    }

    public function calculate_amount($discount, float $price): float
    {
        if ('percent' === $discount->discount_type) {
            $percent = min(100, max(0, (float) $discount->amount));
            return round($price * $percent / 100, 2);
        }
        return min($price, max(0, (float) $discount->amount));
    }

    private function normalize_code(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9\-_]/', '', $code);
        return (string) $code;
    }
}
