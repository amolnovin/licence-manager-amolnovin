<?php
/**
 * Uninstall cleanup.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

$remove_data = (bool) get_option('wplm_remove_data_on_uninstall', false);
if (!$remove_data) {
    return;
}

global $wpdb;
foreach (['categories', 'products', 'discounts', 'license_requests', 'licenses', 'versions', 'orders', 'order_items', 'logs'] as $name) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wplm_' . $name); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
delete_option('wplm_settings');
delete_option('wplm_db_version');

$role = get_role('administrator');
if ($role) {
    $role->remove_cap('manage_wplm');
}
