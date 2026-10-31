<?php
/**
 * WPLM Plugin Updater Client
 *
 * این فایل باید داخل افزونه مشتری قرار بگیرد و باعث می‌شود بروزرسانی در صفحه
 * «افزونه‌ها»ی وردپرس نمایش داده شود.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPLM_Plugin_Updater')) {
    class WPLM_Plugin_Updater {
        protected $config = [];
        protected $last_response = null;

        public function __construct(array $config) {
            $defaults = [
                'api_url'        => '', // https://seller-site.com/wp-json/wplm/v1/updates/check
                'plugin_file'    => '', // __FILE__ فایل اصلی افزونه مشتری
                'plugin_slug'    => '', // my-plugin
                'product_slug'   => '', // slug محصول در سامانه فروش
                'version'        => '1.0.0',
                'license_key'    => '',
                'license_option' => '',
                'license_required' => true,
                'name'           => '',
                'author'         => '',
            ];

            $this->config = wp_parse_args($config, $defaults);
            $this->config['plugin_file'] = plugin_basename($this->config['plugin_file']);
            if (!$this->config['plugin_slug']) {
                $dir = dirname($this->config['plugin_file']);
                $this->config['plugin_slug'] = ('.' === $dir) ? basename($this->config['plugin_file'], '.php') : $dir;
            }
            if (!$this->config['product_slug']) {
                $this->config['product_slug'] = $this->config['plugin_slug'];
            }

            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
            add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        }

        public function check_update($transient) {
            if (!is_object($transient)) {
                $transient = new stdClass();
            }
            if (empty($transient->checked) || empty($this->config['api_url'])) {
                return $transient;
            }

            $data = $this->request_update();
            if (empty($data['success'])) {
                return $transient;
            }

            $this->last_response = $data;
            if (!empty($data['update_available'])) {
                $transient->response[$this->config['plugin_file']] = (object) [
                    'id'            => $this->config['product_slug'],
                    'slug'          => $this->config['plugin_slug'],
                    'plugin'        => $this->config['plugin_file'],
                    'new_version'   => $data['new_version'] ?? $data['version'],
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

        public function plugin_info($result, $action, $args) {
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

        protected function request_update() {
            $license = $this->get_license_key();
            if (!$license && !empty($this->config['license_required'])) {
                return ['success' => false, 'code' => 'license_missing'];
            }

            $cache_key = 'wplm_upd_' . md5($this->config['product_slug'] . '|' . $this->config['version'] . '|' . $license);
            $cached = get_site_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }

            $response = wp_remote_post($this->config['api_url'], [
                'timeout' => 15,
                'headers' => ['Accept' => 'application/json'],
                'body'    => [
                    'product_slug' => $this->config['product_slug'],
                    'plugin_file'  => $this->config['plugin_file'],
                    'version'      => $this->config['version'],
                    'license_key'  => $license,
                    'domain'       => $this->domain(),
                ],
            ]);

            if (is_wp_error($response)) {
                return ['success' => false, 'code' => $response->get_error_code()];
            }

            $data = json_decode((string) wp_remote_retrieve_body($response), true);
            $data = is_array($data) ? $data : ['success' => false, 'code' => 'bad_response'];
            set_site_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);
            return $data;
        }

        protected function get_license_key() {
            if (!empty($this->config['license_key'])) {
                return (string) $this->config['license_key'];
            }
            if (!empty($this->config['license_option'])) {
                return (string) get_option($this->config['license_option'], '');
            }
            return '';
        }

        protected function domain() {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            return $host ? $host : home_url();
        }
    }
}
