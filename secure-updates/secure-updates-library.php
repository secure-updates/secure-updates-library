<?php
/*
Library Name: Secure Updates Library
Description: A library for WordPress plugin authors to easily host their own plugin updates on a secure mirror.
Version: 1.2
Author: Secure Updates Foundation
Text Domain: secure-updates-library
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Secure_Updates_Library')) {
    class Secure_Updates_Library {
        /**
         * Base URL of the secure mirror.
         *
         * @var string
         */
        private $mirror_host;

        /**
         * Plugin slug.
         *
         * @var string
         */
        private $plugin_slug;

        /**
         * Current version of the plugin.
         *
         * @var string
         */
        private $plugin_version;

        /**
         * API Key for authenticating with the secure server.
         *
         * @var string
         */
        private $api_key;

        /**
         * Constructor to initialize the library.
         *
         * @param string $mirror_host    The base URL of the secure mirror.
         * @param string $plugin_version The current version of the plugin.
         * @param string $api_key         (Optional) API key for authenticated requests.
         */
        public function __construct($mirror_host, $plugin_version, $api_key = '') {
            $this->mirror_host = trailingslashit(esc_url_raw($mirror_host));
            $this->plugin_version = sanitize_text_field($plugin_version);
            $this->api_key = sanitize_text_field($api_key);
            $this->plugin_slug = $this->get_plugin_slug(); // Automatically detect the slug

            // Setup hooks for checking plugin updates
            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
            add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
            add_action('admin_notices', [$this, 'display_update_notices']);
        }

        /**
         * Automatically get plugin slug from plugin directory name.
         *
         * @return string
         */
        private function get_plugin_slug() {
            // Assuming the library is included from the main plugin file
            $plugin_file = plugin_basename($this->get_main_plugin_file());
            $parts = explode('/', $plugin_file);
            return sanitize_title_with_dashes($parts[0]);
        }

        /**
         * Get the main plugin file path.
         *
         * @return string
         */
        private function get_main_plugin_file() {
            // This function assumes the library is included directly from the main plugin file
            $backtrace = debug_backtrace();
            foreach ($backtrace as $call) {
                if (isset($call['file']) && strpos($call['file'], WP_PLUGIN_DIR) !== false) {
                    return $call['file'];
                }
            }
            return '';
        }

        /**
         * Fetch the latest plugin information from the secure updates server.
         *
         * @return object|false The plugin information object or false on failure.
         */
        private function fetch_latest_plugin_info() {
            $info_endpoint = $this->mirror_host . 'wp-json/secure-updates-server/v1/info/' . $this->plugin_slug;

            $args = [
                'timeout' => 15,
                'sslverify' => true,
            ];

            // Include API key if provided
            if (!empty($this->api_key)) {
                $args['headers'] = [
                    'Authorization' => 'Bearer ' . $this->api_key,
                ];
            }

            $response = wp_remote_get($info_endpoint, $args);

            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Secure Updates Library] Error fetching plugin info: ' . $response->get_error_message());
                }
                return false;
            }

            $plugin_info = json_decode(wp_remote_retrieve_body($response));

            if (empty($plugin_info->version)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Secure Updates Library] Invalid plugin info received.');
                }
                return false;
            }

            return $plugin_info;
        }

        /**
         * Check for plugin updates.
         *
         * @param stdClass $transient The update transient.
         * @return stdClass
         */
        public function check_for_updates($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }

            // Fetch latest plugin info from the server
            $plugin_info = $this->fetch_latest_plugin_info();

            if (!$plugin_info) {
                return $transient; // Unable to fetch plugin info; exit gracefully
            }

            // Compare versions
            if (version_compare($plugin_info->version, $this->plugin_version, '>')) {
                $response = new stdClass();
                $response->slug = $this->plugin_slug;
                $response->new_version = $plugin_info->version;
                $response->url = esc_url_raw($plugin_info->homepage);
                $response->package = esc_url_raw($plugin_info->download_link);

                $transient->response[$this->plugin_slug . '/' . $this->plugin_slug . '.php'] = $response;

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Secure Updates Library] Update available for ' . $this->plugin_slug . ': ' . $plugin_info->version);
                }
            }

            return $transient;
        }

        /**
         * Provide detailed plugin information.
         *
         * @param mixed    $result The plugin information.
         * @param string   $action The type of request.
         * @param stdClass $args   The query arguments.
         * @return mixed
         */
        public function plugin_information($result, $action, $args) {
            if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
                return $result;
            }

            // Fetch plugin data from the secure mirror
            $info_endpoint = $this->mirror_host . 'wp-json/secure-updates-server/v1/info/' . sanitize_title($this->plugin_slug);

            $request_args = [
                'timeout' => 15,
                'sslverify' => true,
            ];

            // Include API key if provided
            if (!empty($this->api_key)) {
                $request_args['headers'] = [
                    'Authorization' => 'Bearer ' . $this->api_key,
                ];
            }

            $response = wp_remote_get($info_endpoint, $request_args);

            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Secure Updates Library] Error fetching plugin information: ' . $response->get_error_message());
                }
                return $result;
            }

            $plugin_data = json_decode(wp_remote_retrieve_body($response));

            if (isset($plugin_data->name)) {
                $result = new stdClass();
                $result->name = sanitize_text_field($plugin_data->name);
                $result->slug = sanitize_title($plugin_data->slug);
                $result->version = sanitize_text_field($plugin_data->version);
                $result->author = sanitize_text_field($plugin_data->author);
                $result->homepage = esc_url_raw($plugin_data->homepage);
                $result->sections = [
                    'description' => wp_kses_post($plugin_data->description),
                    'installation' => wp_kses_post($plugin_data->installation),
                    'changelog' => wp_kses_post($plugin_data->changelog),
                ];
                $result->download_link = esc_url_raw($plugin_data->download_link);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Secure Updates Library] Fetched plugin information for ' . $this->plugin_slug);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Secure Updates Library] Invalid plugin information received for ' . $this->plugin_slug);
                }
            }

            return $result;
        }

        /**
         * Display update notices in the admin dashboard.
         */
        public function display_update_notices() {
            // Implement logic to display admin notices based on update status
            // This could include success messages for updates or error messages if update checks failed
        }
    }
}
