<?php
namespace WPLM\Repositories;

use WPLM\Database;
use WPLM\Options;
use WPLM\Security;

defined('ABSPATH') || exit;

final class LicenseRepository
{
    /**
     * نوع محصول (plugin/theme/windows) با شناسه محصول.
     */
    private function product_type_for(int $product_id): string
    {
        $product = (new ProductRepository())->find($product_id);
        return $product ? (string) $product->type : '';
    }

    public function create_request(array $data)
    {
        global $wpdb;
        $table = Database::table('license_requests');
        $now = Database::now();
        $token = wp_hash(wp_json_encode($data) . microtime(true) . wp_generate_password(12, false));

        $wpdb->insert($table, [
            'product_id' => (int) $data['product_id'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_mobile' => $data['customer_mobile'],
            'domain' => Security::sanitize_domain($data['domain']),
            'machine_id' => !empty($data['machine_id']) ? Security::sanitize_machine_id((string) $data['machine_id']) : '',
            'status' => 'pending',
            'source' => isset($data['source']) ? sanitize_key($data['source']) : 'manual',
            'order_id' => isset($data['order_id']) ? absint($data['order_id']) : null,
            'request_token' => $token,
            'note' => $data['note'] ?? '',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s']);

        return $this->request((int) $wpdb->insert_id);
    }

    public function request(int $id)
    {
        global $wpdb;
        $table = Database::table('license_requests');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function requests(int $limit = 100): array
    {
        global $wpdb;
        $requests = Database::table('license_requests');
        $products = Database::table('products');
        return $wpdb->get_results($wpdb->prepare("SELECT r.*, p.name AS product_name FROM {$requests} r LEFT JOIN {$products} p ON p.id = r.product_id ORDER BY r.id DESC LIMIT %d", $limit));
    }

    public function approve_request(int $request_id)
    {
        global $wpdb;
        $request = $this->request($request_id);
        if (!$request || 'approved' === $request->status) {
            return null;
        }

        $settings = Options::get();
        $expires = gmdate('Y-m-d H:i:s', strtotime('+' . absint($settings['license_expire_days']) . ' days'));
        $license_key = Security::generate_license_key();
        $now = Database::now();

        // برای محصولات ویندوز، machine_id درخواست در شناسه‌های ماشین لایسنس ذخیره می‌شود.
        $machine_id = !empty($request->machine_id) ? Security::sanitize_machine_id((string) $request->machine_id) : '';
        $machine_ids = [];
        if ($machine_id && Security::looks_like_machine_id($machine_id) && 'windows' === $this->product_type_for((int) $request->product_id)) {
            $machine_ids = [$machine_id];
        }

        $wpdb->insert(Database::table('licenses'), [
            'product_id' => (int) $request->product_id,
            'request_id' => $request_id,
            'license_key' => $license_key,
            'customer_email' => $request->customer_email,
            'customer_mobile' => $request->customer_mobile,
            'allowed_domains' => absint($settings['allowed_domains']),
            'domains' => wp_json_encode(array_values(array_filter([Security::sanitize_domain((string) $request->domain)]))),
            'machine_ids' => wp_json_encode($machine_ids),
            'status' => 'active',
            'expires_at' => $expires,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s']);

        $license_id = (int) $wpdb->insert_id;
        $wpdb->update(Database::table('license_requests'), ['status' => 'approved', 'updated_at' => $now], ['id' => $request_id], ['%s','%s'], ['%d']);

        return $this->find($license_id);
    }

    public function all(int $limit = 200, array $args = []): array
    {
        global $wpdb;
        $licenses = Database::table('licenses');
        $products = Database::table('products');
        $where = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'l.status = %s';
            $params[] = sanitize_key($args['status']);
        }
        if (!empty($args['product_id'])) {
            $where[] = 'l.product_id = %d';
            $params[] = absint($args['product_id']);
        }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(l.license_key LIKE %s OR l.customer_email LIKE %s OR l.customer_mobile LIKE %s OR l.domains LIKE %s OR l.machine_ids LIKE %s OR p.name LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT l.*, p.name AS product_name, p.slug AS product_slug, p.type AS product_type FROM {$licenses} l LEFT JOIN {$products} p ON p.id = l.product_id WHERE " . implode(' AND ', $where) . ' ORDER BY l.id DESC LIMIT %d';
        $params[] = $limit;
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public function find(int $id)
    {
        global $wpdb;
        $table = Database::table('licenses');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public function find_by_key(string $key)
    {
        global $wpdb;
        $table = Database::table('licenses');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE license_key = %s", $key));
    }

    public function create_manual(array $data)
    {
        global $wpdb;
        $now = Database::now();
        $domains = $this->normalize_domains($data['domains'] ?? []);
        $machine_ids = $this->normalize_machine_ids($data['machine_ids'] ?? []);
        $license_key = !empty($data['license_key']) ? sanitize_text_field($data['license_key']) : Security::generate_license_key();

        $wpdb->insert(Database::table('licenses'), [
            'product_id' => absint($data['product_id']),
            'request_id' => null,
            'license_key' => $license_key,
            'customer_email' => sanitize_email((string) ($data['customer_email'] ?? '')),
            'customer_mobile' => sanitize_text_field((string) ($data['customer_mobile'] ?? '')),
            'allowed_domains' => max(1, absint($data['allowed_domains'] ?? 1)),
            'domains' => wp_json_encode($domains),
            'machine_ids' => wp_json_encode($machine_ids),
            'status' => in_array(($data['status'] ?? 'active'), ['active','inactive','suspended','expired'], true) ? $data['status'] : 'active',
            'expires_at' => !empty($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s']);

        return $this->find((int) $wpdb->insert_id);
    }

    public function save_license(int $id, array $data)
    {
        global $wpdb;
        $domains = $this->normalize_domains($data['domains'] ?? []);
        $machine_ids = $this->normalize_machine_ids($data['machine_ids'] ?? []);
        $status = in_array(($data['status'] ?? 'active'), ['active','inactive','suspended','expired'], true) ? $data['status'] : 'active';
        $payload = [
            'product_id' => absint($data['product_id']),
            'customer_email' => sanitize_email((string) ($data['customer_email'] ?? '')),
            'customer_mobile' => sanitize_text_field((string) ($data['customer_mobile'] ?? '')),
            'allowed_domains' => max(1, absint($data['allowed_domains'] ?? 1)),
            'domains' => wp_json_encode($domains),
            'machine_ids' => wp_json_encode($machine_ids),
            'status' => $status,
            'expires_at' => !empty($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null,
            'updated_at' => Database::now(),
        ];

        if (!empty($data['license_key'])) {
            $payload['license_key'] = sanitize_text_field($data['license_key']);
        }

        $formats = [];
        foreach ($payload as $key => $value) {
            $formats[] = in_array($key, ['product_id','allowed_domains'], true) ? '%d' : '%s';
        }

        $wpdb->update(Database::table('licenses'), $payload, ['id' => $id], $formats, ['%d']);
        return $this->find($id);
    }

    public function set_status(int $id, string $status)
    {
        global $wpdb;
        $status = in_array($status, ['active','inactive','suspended','expired'], true) ? $status : 'inactive';
        $wpdb->update(Database::table('licenses'), ['status' => $status, 'updated_at' => Database::now()], ['id' => $id], ['%s','%s'], ['%d']);
        return $this->find($id);
    }

    public function extend(int $id, int $days)
    {
        global $wpdb;
        $license = $this->find($id);
        if (!$license) {
            return null;
        }
        $base = (!empty($license->expires_at) && strtotime($license->expires_at) > time()) ? strtotime($license->expires_at) : time();
        $expires = gmdate('Y-m-d H:i:s', strtotime('+' . max(1, $days) . ' days', $base));
        $wpdb->update(Database::table('licenses'), ['expires_at' => $expires, 'status' => 'active', 'updated_at' => Database::now()], ['id' => $id], ['%s','%s','%s'], ['%d']);
        return $this->find($id);
    }

    public function remove_domain(int $id, string $domain)
    {
        global $wpdb;
        $license = $this->find($id);
        if (!$license) {
            return null;
        }
        $domain = Security::sanitize_domain($domain);
        $domains = array_values(array_filter($this->domains($license), static function ($item) use ($domain) {
            return $item !== $domain;
        }));
        $wpdb->update(Database::table('licenses'), ['domains' => wp_json_encode($domains), 'updated_at' => Database::now()], ['id' => $id], ['%s','%s'], ['%d']);
        return $this->find($id);
    }

    public function remove_machine_id(int $id, string $machine_id)
    {
        global $wpdb;
        $license = $this->find($id);
        if (!$license) {
            return null;
        }
        $machine_id = Security::sanitize_machine_id($machine_id);
        $machine_ids = array_values(array_filter($this->machine_ids($license), static function ($item) use ($machine_id) {
            return $item !== $machine_id;
        }));
        $wpdb->update(Database::table('licenses'), ['machine_ids' => wp_json_encode($machine_ids), 'updated_at' => Database::now()], ['id' => $id], ['%s','%s'], ['%d']);
        return $this->find($id);
    }

    /**
     * دامنه‌های فعال‌شده لایسنس.
     */
    public function domains($license): array
    {
        $domains = json_decode((string) ($license->domains ?? ''), true);
        return is_array($domains) ? array_values(array_filter(array_map([Security::class, 'sanitize_domain'], $domains))) : [];
    }

    /**
     * شناسهٔ ماشین‌های فعال‌شده لایسنس (برای برنامه‌های ویندوز).
     */
    public function machine_ids($license): array
    {
        $ids = json_decode((string) ($license->machine_ids ?? ''), true);
        return is_array($ids) ? array_values(array_filter(array_map([Security::class, 'sanitize_machine_id'], $ids))) : [];
    }

    /**
     * همه‌ی فعال‌سازی‌های لایسنس: دامنه‌ها + machine_ids.
     */
    public function activations($license): array
    {
        return [
            'domains' => $this->domains($license),
            'machine_ids' => $this->machine_ids($license),
        ];
    }

    /**
     * بررسی اعتبار لایسنس با پشتیبانی از machine_id برنامه‌های ویندوز.
     *
     * @param string $key        کلید لایسنس
     * @param int    $product_id شناسه محصول
     * @param string $domain     دامنه رایج (برای plugin/theme)
     * @param string $machine_id شناسهٔ ماشین (برای windows)
     */
    public function validate(string $key, int $product_id, string $domain = '', string $machine_id = ''): array
    {
        $license = $this->find_by_key($key);
        if (!$license) {
            return ['valid' => false, 'code' => 'not_found'];
        }
        if ((int) $license->product_id !== $product_id) {
            return ['valid' => false, 'code' => 'product_mismatch'];
        }
        if ('active' !== $license->status) {
            return ['valid' => false, 'code' => 'inactive'];
        }
        if (!empty($license->expires_at) && strtotime($license->expires_at) < time()) {
            return ['valid' => false, 'code' => 'expired'];
        }

        // فعال‌سازی بر اساس machine_id (برنامه ویندوز).
        if ($machine_id) {
            if (!Security::looks_like_machine_id($machine_id)) {
                return ['valid' => false, 'code' => 'invalid_machine_id'];
            }
            if ('windows' !== $this->product_type_for($product_id)) {
                return ['valid' => false, 'code' => 'product_not_windows'];
            }
            if (!$this->machine_allowed($license, $machine_id)) {
                return ['valid' => false, 'code' => 'machine_limit'];
            }
            return ['valid' => true, 'code' => 'ok', 'license' => $license, 'machine_id' => $machine_id];
        }

        if ($domain && !$this->domain_allowed($license, $domain)) {
            return ['valid' => false, 'code' => 'domain_limit'];
        }
        return ['valid' => true, 'code' => 'ok', 'license' => $license];
    }

    /**
     * فعال‌سازی لایسنس روی دامنه یا machine_id.
     */
    public function activate_domain(string $key, int $product_id, string $domain = '', string $machine_id = ''): array
    {
        global $wpdb;

        if ($machine_id) {
            $machine_id = Security::sanitize_machine_id($machine_id);
            $result = $this->validate($key, $product_id, '', $machine_id);
            if (empty($result['valid'])) {
                return $result;
            }

            $license = $result['license'];
            $machine_ids = $this->machine_ids($license);
            if (!in_array($machine_id, $machine_ids, true)) {
                if (count($machine_ids) >= (int) $license->allowed_domains) {
                    return ['valid' => false, 'code' => 'machine_limit'];
                }
                $machine_ids[] = $machine_id;
                $wpdb->update(Database::table('licenses'), [
                    'machine_ids' => wp_json_encode(array_values(array_unique($machine_ids))),
                    'updated_at' => Database::now(),
                ], ['id' => (int) $license->id], ['%s','%s'], ['%d']);
            }
            return ['valid' => true, 'code' => 'activated', 'machine_id' => $machine_id];
        }

        $domain = Security::sanitize_domain($domain);
        $result = $this->validate($key, $product_id);
        if (empty($result['valid'])) {
            return $result;
        }

        $license = $result['license'];
        $domains = $this->domains($license);
        if (!in_array($domain, $domains, true)) {
            if (count($domains) >= (int) $license->allowed_domains) {
                return ['valid' => false, 'code' => 'domain_limit'];
            }
            $domains[] = $domain;
            $wpdb->update(Database::table('licenses'), [
                'domains' => wp_json_encode(array_values(array_unique($domains))),
                'updated_at' => Database::now(),
            ], ['id' => (int) $license->id], ['%s','%s'], ['%d']);
        }
        return ['valid' => true, 'code' => 'activated'];
    }

    private function domain_allowed($license, string $domain): bool
    {
        $domain = Security::sanitize_domain($domain);
        $domains = $this->domains($license);
        return in_array($domain, $domains, true) || count($domains) < (int) $license->allowed_domains;
    }

    private function machine_allowed($license, string $machine_id): bool
    {
        $machine_ids = $this->machine_ids($license);
        return in_array($machine_id, $machine_ids, true) || count($machine_ids) < (int) $license->allowed_domains;
    }

    private function normalize_domains($domains): array
    {
        if (is_string($domains)) {
            $domains = preg_split('/[\r\n,]+/', $domains);
        }
        $domains = is_array($domains) ? $domains : [];
        $clean = [];
        foreach ($domains as $domain) {
            $domain = Security::sanitize_domain((string) $domain);
            if ($domain) {
                $clean[] = $domain;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * پاک‌سازی شناسه‌های ماشین: فقط اعداد و حروف a-f، حداکثر ۶۴ کاراکتر.
     */
    private function normalize_machine_ids($ids): array
    {
        if (is_string($ids)) {
            $ids = preg_split('/[\r\n,]+/', $ids);
        }
        $ids = is_array($ids) ? $ids : [];
        $clean = [];
        foreach ($ids as $id) {
            $id = Security::sanitize_machine_id((string) $id);
            if ($id) {
                $clean[] = $id;
            }
        }
        return array_values(array_unique($clean));
    }
}
