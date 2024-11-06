<?php
/*
Library Name: Secure Updates Library
Description: A library for WordPress plugin authors to easily host their own plugin updates on a secure mirror.
Version: 2.0
Author: Secure Updates Foundation
Text Domain: secure-updates-library
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Secure_Updates_Library')) {
    // Use the new traits in the main class
    class Secure_Updates_Library {
        use Package_Verification;
        use Enhanced_Logging;
        use Health_Monitoring;
        use Rate_Limiting;

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
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                ]
            ];

            // Include API key if provided
            if (!empty($this->api_key)) {
                $args['headers']['Authorization'] = 'Bearer ' . $this->api_key;
            }

            $response = wp_remote_get($info_endpoint, $args);

            if (is_wp_error($response)) {
                $this->log_error('Error fetching plugin info: ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $this->log_error(sprintf('Server returned %d status code', $response_code));
                return false;
            }

            $plugin_info = json_decode(wp_remote_retrieve_body($response));

            if (empty($plugin_info) || !is_object($plugin_info) || empty($plugin_info->version)) {
                $this->log_error('Invalid plugin info received');
                return false;
            }

            return $this->sanitize_plugin_info($plugin_info);
        }

        /**
         * Log error messages
         *
         * @param string $message Error message
         */
        private function log_error($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Secure Updates Library] %s: %s', $this->plugin_slug, $message));
            }
            $this->store_update_status('error', $message);
        }

        /**
         * Get plugin basename from main file path
         *
         * @return string
         */
        private function get_plugin_basename() {
            static $basename = null;
            if ($basename === null) {
                $basename = plugin_basename($this->get_main_plugin_file());
            }
            return $basename;
        }

        /**
         * Sanitize plugin information from the server
         *
         * @param object $plugin_info Raw plugin information
         * @return object Sanitized plugin information
         */
        private function sanitize_plugin_info($plugin_info) {
            return (object) [
                'name' => sanitize_text_field($plugin_info->name ?? ''),
                'slug' => sanitize_title($plugin_info->slug ?? $this->plugin_slug),
                'version' => sanitize_text_field($plugin_info->version ?? ''),
                'author' => wp_kses($plugin_info->author ?? '', ['a' => ['href' => []]]),
                'homepage' => esc_url_raw($plugin_info->homepage ?? ''),
                'requires' => sanitize_text_field($plugin_info->requires ?? ''),
                'tested' => sanitize_text_field($plugin_info->tested ?? ''),
                'requires_php' => sanitize_text_field($plugin_info->requires_php ?? ''),
                'download_link' => esc_url_raw($plugin_info->download_link ?? ''),
                'sections' => [
                    'description' => wp_kses_post($plugin_info->sections->description ?? ''),
                    'installation' => wp_kses_post($plugin_info->sections->installation ?? ''),
                    'changelog' => wp_kses_post($plugin_info->sections->changelog ?? ''),
                ],
            ];
        }

        public function cleanup() {
            // Clear all transients and options when plugin is deactivated
            global $wpdb;
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'secure_updates_%'");
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_sup_%'");
        }

        private function validate_server_response($response) {
            if (!is_object($response)) return false;
            $required_fields = ['version', 'download_link', 'requires', 'tested'];
            foreach ($required_fields as $field) {
                if (!isset($response->$field)) return false;
            }
            return true;
        }

        private function is_compatible() {
            global $wp_version;
            return version_compare($wp_version, '5.0', '>=');
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

            // Get cached plugin info
            $cache_key = 'sup_' . md5($this->plugin_slug . $this->plugin_version);
            $plugin_info = get_transient($cache_key);

            if (false === $plugin_info) {
                // Fetch latest plugin info from the server
                $plugin_info = $this->fetch_latest_plugin_info();

                if ($plugin_info) {
                    // Cache for 6 hours
                    set_transient($cache_key, $plugin_info, 6 * HOUR_IN_SECONDS);
                } else {
                    // Cache failures for 1 hour to prevent hammering the server
                    set_transient($cache_key, 'failed', HOUR_IN_SECONDS);
                    $this->log_update_error('Failed to fetch plugin info');
                    return $transient;
                }
            } elseif ($plugin_info === 'failed') {
                return $transient;
            }

            // Compare versions
            if (version_compare($plugin_info->version, $this->plugin_version, '>')) {
                $plugin_basename = plugin_basename($this->get_main_plugin_file());

                $response = new stdClass();
                $response->slug = $this->plugin_slug;
                $response->new_version = $plugin_info->version;
                $response->url = esc_url_raw($plugin_info->homepage);
                $response->package = esc_url_raw($plugin_info->download_link);
                $response->tested = isset($plugin_info->tested) ? $plugin_info->tested : get_bloginfo('version');
                $response->requires = isset($plugin_info->requires) ? $plugin_info->requires : '5.0';
                $response->requires_php = isset($plugin_info->requires_php) ? $plugin_info->requires_php : '5.6';

                $transient->response[$plugin_basename] = $response;

                // Store update status for admin notice
                $this->store_update_status('success', sprintf(
                    __('Update available for %s: version %s', 'secure-updates-library'),
                    $this->plugin_slug,
                    $plugin_info->version
                ));
            }

            return $transient;
        }

        /**
         * Store update status for admin notices
         *
         * @param string $status Status of the update (success/error)
         * @param string $message Status message
         */
        private function store_update_status($status, $message) {
            set_transient(
                'secure_updates_' . $this->plugin_slug . '_status',
                ['status' => $status, 'message' => $message],
                30
            );
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

        /**
         * Display diagnostic information in WP Site Health
         */
        public function register_health_tests() {
            add_filter('site_status_tests', function($tests) {
                $tests['direct']['secure_updates_' . $this->plugin_slug] = [
                    'label' => sprintf(
                        __('%s Update System', 'secure-updates-library'),
                        ucfirst($this->plugin_slug)
                    ),
                    'test' => [$this, 'perform_health_test']
                ];
                return $tests;
            });
        }

        /**
         * Perform health test for Site Health
         */
        public function perform_health_test() {
            $health_checks = $this->check_system_health();
            $all_passed = true;
            $result = [
                'label' => sprintf(
                    __('%s Update System', 'secure-updates-library'),
                    ucfirst($this->plugin_slug)
                ),
                'status' => 'good',
                'badge' => [
                    'label' => __('Security', 'secure-updates-library'),
                    'color' => 'blue',
                ],
                'description' => '<p>' . __('Your plugin update system is working correctly.', 'secure-updates-library') . '</p>',
                'actions' => '',
                'test' => 'secure_updates_' . $this->plugin_slug,
            ];

            foreach ($health_checks as $check) {
                if (!$check['status']) {
                    $all_passed = false;
                    $result['status'] = 'critical';
                    break;
                }
            }

            if (!$all_passed) {
                $result['description'] = '<p>' . __('There are issues with your plugin update system.', 'secure-updates-library') . '</p>';
                $result['actions'] = sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('admin.php?page=secure-updates-server'),
                    __('View Details', 'secure-updates-library')
                );
            }

            return $result;
        }
    }

    /**
     * Package verification and backup functionality.
     */
    trait Package_Verification {
        /**
         * Verify package checksum before installation
         *
         * @param string $package Package URL
         * @return bool|WP_Error True on success, WP_Error on failure
         */
        private function verify_package($package) {
            $download_file = download_url($package);

            if (is_wp_error($download_file)) {
                return $download_file;
            }

            // Get expected checksum from server
            $checksum_url = add_query_arg(
                ['action' => 'checksum', 'version' => $this->plugin_version],
                $this->mirror_host . 'wp-json/secure-updates-server/v1/verify_file/' . $this->plugin_slug
            );

            $expected_checksum = wp_remote_get($checksum_url, [
                'headers' => ['Authorization' => 'Bearer ' . $this->api_key]
            ]);

            if (is_wp_error($expected_checksum)) {
                unlink($download_file);
                return $expected_checksum;
            }

            $actual_checksum = hash_file('sha256', $download_file);
            $expected_checksum = trim(wp_remote_retrieve_body($expected_checksum));

            if ($actual_checksum !== $expected_checksum) {
                unlink($download_file);
                return new WP_Error(
                    'checksum_mismatch',
                    __('Package verification failed. Security check did not match.', 'secure-updates-library')
                );
            }

            unlink($download_file);
            return true;
        }

        /**
         * Backup current plugin version before update
         */
        private function backup_current_version() {
            $plugin_dir = plugin_dir_path($this->get_main_plugin_file());
            $backup_dir = WP_CONTENT_DIR . '/upgrade/plugin-backups/';
            $backup_name = $this->plugin_slug . '-' . $this->plugin_version . '-' . date('Y-m-d-H-i-s') . '.zip';

            // Create backup directory if it doesn't exist
            wp_mkdir_p($backup_dir);

            if (!class_exists('PclZip')) {
                require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
            }

            $zip = new PclZip($backup_dir . $backup_name);
            $zip->create($plugin_dir, PCLZIP_OPT_REMOVE_PATH, dirname($plugin_dir));

            return $backup_dir . $backup_name;
        }
    }

    /**
     * Extended error handling and logging
     */
    trait Enhanced_Logging {
        /**
         * Log levels for different types of messages
         */
        private $log_levels = ['error', 'warning', 'info', 'debug'];

        /**
         * Enhanced logging with levels and context
         *
         * @param string $level Log level
         * @param string $message Message to log
         * @param array $context Additional context
         */
        private function enhanced_log($level, $message, $context = []) {
            if (!in_array($level, $this->log_levels)) {
                $level = 'info';
            }

            $log_entry = [
                'timestamp' => current_time('mysql'),
                'level' => $level,
                'message' => $message,
                'plugin' => $this->plugin_slug,
                'version' => $this->plugin_version,
                'context' => $context
            ];

            // Store in WordPress options for admin viewing
            $logs = get_option('secure_updates_logs_' . $this->plugin_slug, []);
            array_unshift($logs, $log_entry);
            $logs = array_slice($logs, 0, 100); // Keep last 100 logs
            update_option('secure_updates_logs_' . $this->plugin_slug, $logs);

            // Also write to debug.log if enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    sprintf(
                        '[Secure Updates Library] [%s] %s: %s | Context: %s',
                        strtoupper($level),
                        $this->plugin_slug,
                        $message,
                        json_encode($context)
                    )
                );
            }
        }
    }

    /**
     * Health monitoring and diagnostics
     */
    trait Health_Monitoring {
        /**
         * Run system health checks
         *
         * @return array Health check results
         */
        public function check_system_health() {
            $results = [];

            // Check WordPress version compatibility
            $results['wp_version'] = [
                'status' => version_compare(get_bloginfo('version'), '5.0', '>='),
                'message' => sprintf(
                    __('WordPress version %s', 'secure-updates-library'),
                    get_bloginfo('version')
                )
            ];

            // Check PHP version
            $results['php_version'] = [
                'status' => version_compare(PHP_VERSION, '5.6', '>='),
                'message' => sprintf(
                    __('PHP version %s', 'secure-updates-library'),
                    PHP_VERSION
                )
            ];

            // Check SSL/TLS capabilities
            $results['ssl_support'] = [
                'status' => extension_loaded('openssl'),
                'message' => __('OpenSSL extension', 'secure-updates-library')
            ];

            // Check write permissions
            $plugin_dir = plugin_dir_path($this->get_main_plugin_file());
            $results['write_permissions'] = [
                'status' => wp_is_writable($plugin_dir),
                'message' => sprintf(
                    __('Write permissions for %s', 'secure-updates-library'),
                    $plugin_dir
                )
            ];

            // Check server connection
            $connection_test = $this->test_server_connection();
            $results['server_connection'] = [
                'status' => $connection_test['connection']['status'] === 'success',
                'message' => __('Update server connection', 'secure-updates-library')
            ];

            return $results;
        }
    }

    /**
     * Rate limiting for API requests
     */
    trait Rate_Limiting {
        /**
         * Check and apply rate limiting
         *
         * @param string $endpoint Endpoint being accessed
         * @return bool|WP_Error True if allowed, WP_Error if rate limited
         */
        private function check_rate_limit($endpoint) {
            $rate_limit_key = 'sup_rate_' . md5($this->plugin_slug . $endpoint);
            $minute_limit = 30; // Maximum requests per minute

            $current_count = get_transient($rate_limit_key);

            if ($current_count === false) {
                set_transient($rate_limit_key, 1, MINUTE_IN_SECONDS);
                return true;
            }

            if ($current_count >= $minute_limit) {
                return new WP_Error(
                    'rate_limited',
                    __('Too many requests. Please try again later.', 'secure-updates-library')
                );
            }

            set_transient($rate_limit_key, $current_count + 1, MINUTE_IN_SECONDS);
            return true;
        }
    }
}
