<?php
namespace WPLM;

defined('ABSPATH') || exit;

final class Database
{
    public static function table(string $name): string
    {
        global $wpdb;
        $allowed = ['categories', 'products', 'discounts', 'license_requests', 'licenses', 'versions', 'orders', 'order_items', 'logs'];
        if (!in_array($name, $allowed, true)) {
            wp_die(esc_html__('Invalid table name.', 'wp-plugin-market-manager'));
        }
        return $wpdb->prefix . 'wplm_' . $name;
    }

    public static function now(): string
    {
        return current_time('mysql');
    }
}
