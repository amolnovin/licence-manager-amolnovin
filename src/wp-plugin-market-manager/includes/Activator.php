<?php
namespace WPLM;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        self::create_tables();
        self::add_caps();
        Options::ensure_defaults();
        update_option('wplm_db_version', WPLM_VERSION, false);
        update_option('wplm_needs_default_pages', '1', false);
    }

    public static function deactivate(): void
    {
        // Keep data intentionally. Cleanup is handled by uninstall.php.
    }

    public static function maybe_upgrade(): void
    {
        if (get_option('wplm_db_version') === WPLM_VERSION) {
            return;
        }

        self::create_tables();
        self::add_caps();
        Options::ensure_defaults();
        update_option('wplm_db_version', WPLM_VERSION, false);
        update_option('wplm_needs_default_pages', '1', false);
    }

    public static function maybe_create_default_pages(): void
    {
        if ('1' !== (string) get_option('wplm_needs_default_pages', '0')) {
            return;
        }

        global $wp_rewrite;
        if (!did_action('init') || !is_object($wp_rewrite)) {
            return;
        }

        self::create_default_pages();
        delete_option('wplm_needs_default_pages');
    }

    private static function add_caps(): void
    {
        $role = get_role('administrator');
        if ($role && !$role->has_cap('manage_wplm')) {
            $role->add_cap('manage_wplm');
        }
    }

    private static function create_default_pages(): void
    {
        $settings = Options::get();
        $pages = [
            'store_page_id' => ['title' => 'فروشگاه افزونه و قالب', 'slug' => 'wplm-store', 'content' => '[wplm_store]'],
            'account_page_id' => ['title' => 'حساب کاربری محصولات', 'slug' => 'wplm-account', 'content' => '[wplm_account]'],
            'payment_result_page_id' => ['title' => 'نتیجه خرید', 'slug' => 'wplm-payment-result', 'content' => '[wplm_payment_result]'],
        ];

        foreach ($pages as $key => $page) {
            $existing_id = !empty($settings[$key]) ? absint($settings[$key]) : 0;
            if ($existing_id && get_post($existing_id)) {
                continue;
            }

            $found = get_page_by_path($page['slug']);
            if ($found) {
                $settings[$key] = (int) $found->ID;
                continue;
            }

            $page_id = wp_insert_post([
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
            ], true);

            if (!is_wp_error($page_id) && $page_id) {
                $settings[$key] = (int) $page_id;
            }
        }

        Options::update($settings);
    }

    private static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $categories = Database::table('categories');
        $products = Database::table('products');
        $discounts = Database::table('discounts');
        $requests = Database::table('license_requests');
        $licenses = Database::table('licenses');
        $versions = Database::table('versions');
        $orders = Database::table('orders');
        $order_items = Database::table('order_items');
        $logs = Database::table('logs');

        $sql = [];
        $sql[] = "CREATE TABLE {$categories} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'all',
            description LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY type (type),
            KEY license_mode (license_mode),
            KEY is_visible (is_visible)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$products} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id BIGINT UNSIGNED NULL,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'plugin',
            cover_url TEXT NULL,
            short_description TEXT NULL,
            description LONGTEXT NULL,
            version VARCHAR(50) NOT NULL DEFAULT '1.0.0',
            price DECIMAL(18,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'IRT',
            license_mode VARCHAR(20) NOT NULL DEFAULT 'required',
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY category_id (category_id),
            KEY status (status),
            KEY type (type),
            KEY license_mode (license_mode),
            KEY is_visible (is_visible)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$discounts} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            title VARCHAR(190) NULL,
            discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            product_id BIGINT UNSIGNED NULL,
            category_id BIGINT UNSIGNED NULL,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            usage_limit INT UNSIGNED NOT NULL DEFAULT 0,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY product_id (product_id),
            KEY category_id (category_id),
            KEY status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$requests} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(190) NULL,
            customer_email VARCHAR(190) NULL,
            customer_mobile VARCHAR(50) NULL,
            domain VARCHAR(190) NULL,
            machine_id VARCHAR(64) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            source VARCHAR(30) NOT NULL DEFAULT 'manual',
            order_id BIGINT UNSIGNED NULL,
            request_token VARCHAR(100) NOT NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_token (request_token),
            KEY product_id (product_id),
            KEY status (status),
            KEY customer_email (customer_email),
            KEY order_id (order_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$licenses} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            request_id BIGINT UNSIGNED NULL,
            license_key VARCHAR(120) NOT NULL,
            customer_email VARCHAR(190) NULL,
            customer_mobile VARCHAR(50) NULL,
            allowed_domains INT UNSIGNED NOT NULL DEFAULT 1,
            domains LONGTEXT NULL,
            machine_ids LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY license_key (license_key),
            KEY product_id (product_id),
            KEY status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$orders} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            license_id BIGINT UNSIGNED NULL,
            order_key VARCHAR(100) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            original_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            discount_code VARCHAR(80) NULL,
            discount_id BIGINT UNSIGNED NULL,
            gateway_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'IRT',
            customer_name VARCHAR(190) NULL,
            customer_email VARCHAR(190) NULL,
            customer_mobile VARCHAR(50) NULL,
            domain VARCHAR(190) NULL,
            gateway VARCHAR(30) NOT NULL DEFAULT 'zibal',
            track_id VARCHAR(100) NULL,
            ref_number VARCHAR(100) NULL,
            card_number VARCHAR(100) NULL,
            paid_at DATETIME NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_key (order_key),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY discount_id (discount_id),
            KEY status (status),
            KEY track_id (track_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$order_items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            license_id BIGINT UNSIGNED NULL,
            product_name VARCHAR(190) NULL,
            product_slug VARCHAR(120) NULL,
            product_type VARCHAR(20) NULL,
            license_mode VARCHAR(20) NOT NULL DEFAULT 'required',
            qty INT UNSIGNED NOT NULL DEFAULT 1,
            price DECIMAL(18,2) NOT NULL DEFAULT 0,
            original_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY license_id (license_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$versions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            version VARCHAR(50) NOT NULL,
            package_url TEXT NULL,
            file_path TEXT NULL,
            file_name VARCHAR(255) NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            changelog LONGTEXT NULL,
            requires_wp VARCHAR(20) NULL,
            tested_wp VARCHAR(20) NULL,
            requires_php VARCHAR(20) NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY is_current (is_current),
            KEY version (version)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            event VARCHAR(120) NOT NULL,
            context LONGTEXT NULL,
            ip VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY event (event),
            KEY level (level)
        ) {$charset};";

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }
}
