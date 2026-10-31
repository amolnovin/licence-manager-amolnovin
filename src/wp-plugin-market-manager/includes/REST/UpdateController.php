<?php
namespace WPLM\REST;

use WPLM\Options;
use WPLM\Repositories\LicenseRepository;
use WPLM\Repositories\ProductRepository;
use WPLM\Repositories\VersionRepository;
use WPLM\Security;
use WPLM\Services\PackageService;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class UpdateController
{
    public function register_routes(): void
    {
        register_rest_route('wplm/v1', '/updates/check', [
            'methods' => 'POST',
            'callback' => [$this, 'check'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function check(WP_REST_Request $request): WP_REST_Response
    {
        if (!Security::rate_limit('update_check', 120, 300)) {
            return new WP_REST_Response(['success' => false, 'code' => 'rate_limited'], 429);
        }

        $product_slug = sanitize_title($request->get_param('product_slug'));
        $plugin_file = sanitize_text_field((string) $request->get_param('plugin_file'));
        $installed = sanitize_text_field((string) $request->get_param('version'));
        $license_key = sanitize_text_field((string) $request->get_param('license_key'));
        $domain = Security::sanitize_domain((string) $request->get_param('domain'));

        $product = (new ProductRepository())->find_by_slug($product_slug);
        if (!$product || 'active' !== $product->status) {
            return new WP_REST_Response(['success' => false, 'code' => 'product_not_found'], 404);
        }

        $license_required = 'none' !== ($product->license_mode ?? 'required');
        if ($license_required) {
            $valid = (new LicenseRepository())->validate($license_key, (int) $product->id, $domain);
            if (empty($valid['valid'])) {
                return new WP_REST_Response(['success' => false, 'code' => $valid['code']], 403);
            }
        }

        $row = (new VersionRepository())->current_for_product((int) $product->id);
        if (!$row) {
            return new WP_REST_Response([
                'success' => true,
                'update_available' => false,
                'license_required' => $license_required,
                'latest_version' => $product->version,
            ], 200);
        }

        $available = version_compare($row->version, $installed, '>');
        $download_url = $available ? PackageService::download_url((int) $row->id, $license_key, $domain) : '';
        $homepage = add_query_arg('product', $product->slug, Options::page_url('store_page_id'));

        return new WP_REST_Response([
            'success' => true,
            'update_available' => $available,
            'license_required' => $license_required,
            'name' => $product->name,
            'slug' => $product->slug,
            'product_slug' => $product->slug,
            'plugin' => $plugin_file,
            'plugin_file' => $plugin_file,
            'version' => $row->version,
            'latest_version' => $row->version,
            'new_version' => $row->version,
            'package' => $download_url,
            'package_url' => $download_url,
            'download_url' => $download_url,
            'url' => $homepage,
            'homepage' => $homepage,
            'requires' => $row->requires_wp,
            'requires_wp' => $row->requires_wp,
            'tested' => $row->tested_wp,
            'tested_wp' => $row->tested_wp,
            'requires_php' => $row->requires_php,
            'changelog' => wp_kses_post($row->changelog),
            'sections' => [
                'description' => wp_kses_post($product->description),
                'changelog' => wp_kses_post($row->changelog),
            ],
        ], 200);
    }
}
