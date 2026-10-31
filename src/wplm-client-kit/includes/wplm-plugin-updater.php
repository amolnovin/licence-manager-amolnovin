<?php
/**
 * WPLM Plugin Updater Client
 *
 * Shows updates for commercial plugins in WordPress Plugins/Updates screens.
 * Compatible with wp-plugin-market-manager.
 *
 * @version 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPLM_Client_Kit_Updater')) {
    final class WPLM_Client_Kit_Updater
    {
        private $config = [];
        private $last_response = null;

        public function __construct(array $config)
        {
            $defaults = [
                'api_url'        => '', // https://your-shop.com/wp-json/wplm/v1/updates/check
                'plugin_file'    => '', // __FILE__ main plugin file
                'plugin_slug'    => '',
                'product_slug'   => '',
                'version'        => '1.0.0',
                'license_key'    => '',
                'license_option' => '',
                'license_required' => true,
                'machine_id'      => '',
                'name'           => '',
                'author'         => '',
                'cache_ttl'      => 6 * HOUR_IN_SECONDS,
                'timeout'        => 15,
                'require_ssl'    => true,
                'allow_insecure' => false,
            ];

            $this->config = wp_parse_args($config, $defaults);
            $this->config['api_url'] = $this->normalize_api_url((string) $this->config['api_url']);
            $this->config['plugin_file'] = $this->config['plugin_file'] ? plugin_basename($this->config['plugin_file']) : '';
            $this->config['product_slug'] = sanitize_key($this->config['product_slug']);

            if (!$this->config['plugin_slug']) {
                $dir = dirname($this->config['plugin_file']);
                $this->config['plugin_slug'] = ('.' === $dir) ? basename($this->config['plugin_file'], '.php') : $dir;
            }
            if (!$this->config['product_slug']) {
                $this->config['product_slug'] = sanitize_key($this->config['plugin_slug']);
            }

            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
            add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
            add_action('upgrader_process_complete', [$this, 'clear_cache_after_update'], 10, 2);
        }

        public function check_update($transient)
        {
            if (!is_object($transient)) {
                $transient = new stdClass();
            }
            if (empty($transient->checked) || empty($this->config['plugin_file']) || !$this->api_is_allowed()) {
                return $transient;
            }

            $data = $this->request_update();
            if (empty($data['success'])) {
                return $transient;
            }

            $this->last_response = $data;
            $version = $data['new_version'] ?? $data['version'] ?? '';

            if (!empty($data['update_available']) && $version) {
                $transient->response[$this->config['plugin_file']] = (object) [
                    'id'            => $this->config['product_slug'],
                    'slug'          => $this->config['plugin_slug'],
                    'plugin'        => $this->config['plugin_file'],
                    'new_version'   => $version,
                    'url'           => $data['url'] ?? '',
                    'package'       => $data['package'] ?? $data['download_url'] ?? '',
                    'tested'        => $data['tested'] ?? '',
                    'requires'      => $data['requires'] ?? '',
                    'requires_php'  => $data['requires_php'] ?? '',
                ];
            } else {
                $transient->no_update[$this->config['plugin_file']] = (object) [
                    'id'          => $this->config['product_slug'],
                    'slug'        => $this->config['plugin_slug'],
                    'plugin'      => $this->config['plugin_file'],
                    'new_version' => $this->config['version'],
                    'url'         => $data['url'] ?? '',
                    'package'     => '',
                ];
            }

            return $transient;
        }

        public function plugin_info($result, $action, $args)
        {
            if ('plugin_information' !== $action || empty($args->slug) || $args->slug !== $this->config['plugin_slug']) {
                return $result;
            }

            $data = $this->last_response ?: $this->request_update();
            if (empty($data['success'])) {
                return $result;
            }

            return (object) [
                'name'          => $data['name'] ?? $this->config['name'],
                'slug'          => $this->config['plugin_slug'],
                'version'       => $data['version'] ?? $this->config['version'],
                'author'        => $this->config['author'],
                'homepage'      => $data['homepage'] ?? $data['url'] ?? '',
                'requires'      => $data['requires'] ?? '',
                'tested'        => $data['tested'] ?? '',
                'requires_php'  => $data['requires_php'] ?? '',
                'download_link' => $data['download_url'] ?? $data['package'] ?? '',
                'sections'      => $data['sections'] ?? [
                    'description' => '',
                    'changelog'   => $data['changelog'] ?? '',
                ],
            ];
        }

        public function clear_cache_after_update($upgrader, $options): void
        {
            if (!empty($options['plugins']) && is_array($options['plugins']) && in_array($this->config['plugin_file'], $options['plugins'], true)) {
                delete_site_transient($this->cache_key());
            }
        }

        private function request_update(): array
        {
            $license = $this->get_license_key();
            if (!$license && !empty($this->config['license_required'])) {
                return ['success' => false, 'code' => 'license_missing'];
            }

            $cached = get_site_transient($this->cache_key());
            if (is_array($cached) && !empty($cached['success'])) {
                return $cached;
            }

            $primary = $this->config['api_url'];
            $result  = $this->do_request($primary, $license);

            // خطای موقت سمت سرور (مثلاً خطای پایگاه‌داده) -> با ?rest_route= دوباره تلاش کن
            if (!empty($result['transient'])) {
                $fallback = $this->fallback_url($primary);
                if ($fallback) {
                    $retry = $this->do_request($fallback, $license);
                    if (empty($retry['transient'])) {
                        $retry['used_fallback'] = true;
                        $result = $retry;
                    }
                }
            }

            if (!empty($result['success'])) {
                $result['http_status'] = $result['http_status'] ?? 200;
                set_site_transient($this->cache_key(), $result, (int) $this->config['cache_ttl']);
                set_site_transient($this->last_good_key(), $result, 24 * HOUR_IN_SECONDS);
                return $result;
            }

            // خطای موقت: اگر آخرین پاسخ موفقی موجود است، همان را برگردان تا آپدیت از بین نرود.
            if (!empty($result['transient'])) {
                $last_good = get_site_transient($this->last_good_key());
                if (is_array($last_good) && !empty($last_good['success'])) {
                    return $last_good;
                }
                set_site_transient($this->cache_key(), $result, 15 * MINUTE_IN_SECONDS);
                return $result;
            }

            // خطای واقعی (مثلاً لایسنس نامعتبر) -> کش کوتاه
            set_site_transient($this->cache_key(), $result, HOUR_IN_SECONDS);
            return $result;
        }

        /**
         * یک تلاش واحد HTTP برای بررسی آپدیت و تبدیل پاسخ به ساختار یکپارچه.
         */
        private function do_request(string $url, string $license): array
        {
            $response = wp_remote_post($url, [
                'timeout'     => (int) $this->config['timeout'],
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'WPLM-Plugin-Updater/1.2; ' . home_url('/'),
                ],
                'body'        => [
                    'product_slug' => $this->config['product_slug'],
                    'plugin_file'  => $this->config['plugin_file'],
                    'version'      => $this->config['version'],
                    'license_key'  => $license,
                    'domain'       => $this->domain(),
                    'machine_id'   => $this->config['machine_id'],
                ],
            ]);

            if (is_wp_error($response)) {
                return [
                    'success'     => false,
                    'code'        => 'network_error',
                    'message'     => $this->clean($response->get_error_message()),
                    'http_status' => 0,
                    'transient'   => true,
                ];
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $body_raw    = (string) wp_remote_retrieve_body($response);
            $decoded     = json_decode($body_raw, true);

            // صفحه‌ی خطای سرور (مثلاً خطای پایگاه‌داده) یا 5xx -> خطای موقت
            if (!is_array($decoded) || $status_code >= 500) {
                $db_error = $this->looks_like_server_error($body_raw);
                return [
                    'success'     => false,
                    'code'        => $db_error ? 'server_db_error' : 'server_error',
                    'message'     => $db_error ? 'خطای موقت پایگاه‌داده سرور' : 'خطای موقت سمت سرور',
                    'http_status' => $status_code,
                    'transient'   => true,
                ];
            }

            $data = $decoded;
            $data['http_status'] = $status_code;
            $data['transient']   = false;
            if ($status_code >= 400) {
                $data['success'] = false;
            }
            if (isset($data['message'])) {
                $data['message'] = $this->clean((string) $data['message']);
            }
            return $data;
        }

        private function looks_like_server_error(string $body_raw): bool
        {
            $stripped = trim(strip_tags($body_raw));
            if (strpos($stripped, 'خطا در برقراری ارتباط با پایگاه') !== false) {
                return true;
            }
            if (preg_match('/error\s+establishing\s+a\s+connection\s+to\s+the\s+database|WordPress\s+Database\s+Error|database_connection/i', $stripped)) {
                return true;
            }
            return false;
        }

        private function fallback_url(string $url): string
        {
            if (strpos($url, '/wp-json') === false) {
                return '';
            }
            $scheme = (string) (wp_parse_url($url, PHP_URL_SCHEME) ?: 'https');
            $host   = (string) (wp_parse_url($url, PHP_URL_HOST) ?: '');
            $path   = (string) (wp_parse_url($url, PHP_URL_PATH) ?: '');
            if ($host && strpos($path, '/wp-json') === 0) {
                return $scheme . '://' . $host . '/?rest_route=' . substr($path, strlen('/wp-json'));
            }
            return '';
        }

        private function clean($value): string
        {
            $value = strip_tags((string) $value);
            $value = preg_replace('/\s+/u', ' ', (string) $value);
            $value = trim((string) $value);
            return function_exists('mb_substr') ? mb_substr($value, 0, 200) : substr($value, 0, 200);
        }

        private function last_good_key(): string
        {
            return 'wplm_upd_lastgood_' . md5($this->config['product_slug'] . '|' . $this->config['plugin_file'] . '|' . $this->get_license_key());
        }

        private function get_license_key(): string
        {
            if (!empty($this->config['license_key'])) {
                return (string) $this->config['license_key'];
            }
            if (!empty($this->config['license_option'])) {
                return (string) get_option($this->config['license_option'], '');
            }
            return '';
        }

        private function cache_key(): string
        {
            return 'wplm_upd_' . md5($this->config['product_slug'] . '|' . $this->config['plugin_file'] . '|' . $this->config['version'] . '|' . $this->get_license_key());
        }

        private function normalize_api_url(string $url): string
        {
            $url = trim($url);
            $url = preg_replace('/\s+/', '', $url);

            if ($url && 0 === strpos($url, '//')) {
                $url = 'https:' . $url;
            }

            if ($url && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }

            return esc_url_raw($url);
        }

        private function api_is_allowed(): bool
        {
            if (empty($this->config['api_url'])) {
                return false;
            }

            $parts = wp_parse_url($this->config['api_url']);
            if (empty($parts['scheme']) || empty($parts['host'])) {
                return false;
            }

            $scheme = strtolower((string) $parts['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                return false;
            }

            if ($this->config['require_ssl'] && !$this->config['allow_insecure'] && 'https' !== $scheme) {
                return false;
            }

            return true;
        }

        private function domain(): string
        {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            $host = $host ? strtolower($host) : home_url();
            $host = preg_replace('/^www\./', '', (string) $host);
            return sanitize_text_field($host);
        }
    }
}

// Backward-compatible alias for older snippets.
// Do not extend WPLM_Client_Kit_Updater because it is final; use class_alias instead.
if (!class_exists('WPLM_Plugin_Updater') && class_exists('WPLM_Client_Kit_Updater')) {
    class_alias('WPLM_Client_Kit_Updater', 'WPLM_Plugin_Updater');
}
