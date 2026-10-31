<?php
namespace WPLM\REST;

use WPLM\Repositories\LicenseRepository;
use WPLM\Repositories\ProductRepository;
use WPLM\Security;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class LicenseController
{
    public function register_routes(): void
    {
        register_rest_route('wplm/v1', '/license/request', [
            'methods' => 'POST',
            'callback' => [$this, 'request_license'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('wplm/v1', '/license/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activate'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('wplm/v1', '/license/check', [
            'methods' => 'POST',
            'callback' => [$this, 'check'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function request_license(WP_REST_Request $request): WP_REST_Response
    {
        if (!Security::rate_limit('license_request', 15, 300)) {
            return new WP_REST_Response(['success' => false, 'code' => 'rate_limited'], 429);
        }

        $product_slug = sanitize_title($request->get_param('product_slug'));
        $product = (new ProductRepository())->find_by_slug($product_slug);
        if (!$product || 'active' !== $product->status) {
            return new WP_REST_Response(['success' => false, 'code' => 'product_not_found'], 404);
        }

        $email = sanitize_email((string) $request->get_param('customer_email'));
        if (!$email || !is_email($email)) {
            return new WP_REST_Response(['success' => false, 'code' => 'invalid_email'], 422);
        }

        $machine_id = Security::sanitize_machine_id((string) $request->get_param('machine_id'));
        if ($machine_id && !Security::looks_like_machine_id($machine_id) && 'windows' !== (new ProductRepository())->find((int) $product->id)->type) {
            return new WP_REST_Response(['success' => false, 'code' => 'invalid_machine_id'], 422);
        }

        $created = (new LicenseRepository())->create_request([
            'product_id' => (int) $product->id,
            'customer_name' => sanitize_text_field((string) $request->get_param('customer_name')),
            'customer_email' => $email,
            'customer_mobile' => sanitize_text_field((string) $request->get_param('customer_mobile')),
            'domain' => Security::sanitize_domain((string) $request->get_param('domain')),
            'machine_id' => $machine_id,
            'note' => sanitize_textarea_field((string) $request->get_param('note')),
        ]);

        return new WP_REST_Response(['success' => true, 'request_id' => (int) $created->id, 'status' => $created->status], 201);
    }

    public function activate(WP_REST_Request $request): WP_REST_Response
    {
        if (!Security::rate_limit('license_activate', 40, 300)) {
            return new WP_REST_Response(['success' => false, 'code' => 'rate_limited'], 429);
        }

        $product_slug = sanitize_title($request->get_param('product_slug'));
        $product = (new ProductRepository())->find_by_slug($product_slug);
        if (!$product) {
            return new WP_REST_Response(['success' => false, 'code' => 'product_not_found'], 404);
        }
        $result = (new LicenseRepository())->activate_domain(
            sanitize_text_field((string) $request->get_param('license_key')),
            (int) $product->id,
            Security::sanitize_domain((string) $request->get_param('domain')),
            Security::sanitize_machine_id((string) $request->get_param('machine_id'))
        );
        return new WP_REST_Response(['success' => (bool) $result['valid'], 'code' => $result['code'], 'machine_id' => $result['machine_id'] ?? null], $result['valid'] ? 200 : 403);
    }

    public function check(WP_REST_Request $request): WP_REST_Response
    {
        if (!Security::rate_limit('license_check', 80, 300)) {
            return new WP_REST_Response(['success' => false, 'code' => 'rate_limited'], 429);
        }

        $product_slug = sanitize_title($request->get_param('product_slug'));
        $product = (new ProductRepository())->find_by_slug($product_slug);
        if (!$product) {
            return new WP_REST_Response(['success' => false, 'code' => 'product_not_found'], 404);
        }
        $result = (new LicenseRepository())->validate(
            sanitize_text_field((string) $request->get_param('license_key')),
            (int) $product->id,
            Security::sanitize_domain((string) $request->get_param('domain')),
            Security::sanitize_machine_id((string) $request->get_param('machine_id'))
        );
        return new WP_REST_Response(['success' => (bool) $result['valid'], 'code' => $result['code'], 'machine_id' => $result['machine_id'] ?? null], $result['valid'] ? 200 : 403);
    }
}
