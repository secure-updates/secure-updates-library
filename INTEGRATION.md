# Secure Updates Library Integration Guide

## Overview

The Secure Updates Library allows WordPress plugin authors to deliver updates through their own Secure Updates Server instead of WordPress.org. The library handles version checking, update delivery, and plugin information display.

## Integration Steps

### 1. Add Integration Code
Copy and paste the following code block **exactly as shown** into your plugin's main file. You will only need to modify three specific values (marked with 🔄):

```php
/**
 * BEGIN: Secure Updates Library Integration
 * Handles plugin updates through a secure, private update server.
 * @link https://github.com/your-repo/secure-updates Documentation
 */
if (!isset($secure_updates_instances) || !is_array($secure_updates_instances)) {
    $secure_updates_instances = [];
}

// Include the Secure Updates Library
include_once trailingslashit(plugin_dir_path(__FILE__)) . 'secure-updates/secure-updates-library.php';

// Initialize the Secure Updates Library with API key
$secure_updates_instances[] = new Secure_Updates_Library(
    'https://your-secure-updates-server.com', // 🔄 UPDATE: Your server URL
    '1.2.3',                                  // 🔄 UPDATE: Your plugin version
    'YOUR_API_KEY_HERE'                       // 🔄 UPDATE: Your API key
);
/** END: Secure Updates Library Integration */
```

### 2. Update Required Values
Only modify these three values in the code above:

1. **Server URL** (Line 13)
   - Replace `'https://your-secure-updates-server.com'` with the URL of the WordPress installation with [Secure Update Server](https://github.com/secure-updates/secure-updates-server) installed
   - Must be HTTPS
   - Example: `'https://updates.yourcompany.com'`

2. **Plugin Version** (Line 14)
   - Replace `'1.2.3'` with your plugin's current version
   - Must match the version in your plugin header
   - Example: `'2.0.1'`

3. **API Key** (Line 15)
   - Replace `'YOUR_API_KEY_HERE'` with your actual API key
   - Get this from your Secure Updates Server
   - Example: `'sk_live_abc123def456'`

### ⚠️ Important Notes
- Do not modify any other parts of the integration code
- Keep the array initialization and library inclusion exactly as shown
- Maintain the comment blocks and structure
- The library path (`secure-updates/secure-updates-library.php`) should match your directory structure


### 2. Directory Structure
Ensure your plugin follows this structure:
```
your-plugin/
├── your-plugin.php              # Main plugin file with above code
├── secure-updates/             # Directory for updates library
│   └── secure-updates-library.php  # The library file
└── [other plugin files...]
```

### Important Notes

1. **Global Instance Management**
    - The `$secure_updates_instances` array ensures multiple plugins can use the library
    - Each plugin's instance is tracked separately
    - No conflicts between different plugins using the library

2. **Version Synchronization**
    - Keep the version number in your plugin header synchronized with the version passed to the library
    - Example: If plugin header shows `Version: 1.2.3`, use `'1.2.3'` in the library initialization

3. **Path Management**
    - Uses `plugin_dir_path(__FILE__)` to ensure correct path regardless of WordPress installation
    - `trailingslashit()` ensures proper directory separator


## Example Implementation (Updated)

```php
<?php
/*
Plugin Name: Your Amazing Plugin
Plugin URI: https://your-plugin-site.com
Description: Your plugin description
Version: 1.2.3
Author: Your Name
Author URI: https://your-site.com
License: GPL2
*/

/**
 * BEGIN: Secure Updates Library Integration
 * Handles plugin updates through a secure, private update server.
 * @link https://github.com/your-repo/secure-updates Documentation
 */
if (!isset($secure_updates_instances) || !is_array($secure_updates_instances)) {
    $secure_updates_instances = [];
}

// Include the Secure Updates Library
include_once trailingslashit(plugin_dir_path(__FILE__)) . 'secure-updates/secure-updates-library.php';

// Initialize the Secure Updates Library with API key
$secure_updates_instances[] = new Secure_Updates_Library(
    'https://your-secure-updates-server.com', // 🔄 UPDATE: Your server URL
    '1.2.3',                                  // 🔄 UPDATE: Your plugin version
    'YOUR_API_KEY_HERE'                       // 🔄 UPDATE: Your API key
);
/** END: Secure Updates Library Integration */

// Your plugin's main functionality
class Your_Amazing_Plugin {
    public function __construct() {
        // Plugin initialization code
    }
    
    // Plugin methods...
}

// Initialize your plugin
new Your_Amazing_Plugin();
```


## Library Features

### Automatic Plugin Detection
The library automatically detects your plugin's slug from its directory name:
```php
private function get_plugin_slug() {
    $plugin_file = plugin_basename($this->get_main_plugin_file());
    $parts = explode('/', $plugin_file);
    return sanitize_title_with_dashes($parts[0]);
}
```

### Update Checking
Automatically checks for updates by communicating with your Secure Updates Server:

```php
// Example usage - happens automatically
$plugin_info = $this->fetch_latest_plugin_info();
if (version_compare($plugin_info->version, $this->plugin_version, '>')) {
    // Update is available
}
```

## Server Endpoint Integration

### 1. Plugin Information Endpoint
Fetches plugin metadata from `/wp-json/secure-updates-server/v1/info/{slug}`

**Response Format Expected:**
```json
{
    "name": "Your Plugin Name",
    "slug": "your-plugin-slug",
    "version": "1.1.0",
    "author": "Your Name",
    "homepage": "https://your-site.com",
    "description": "Plugin description",
    "installation": "Installation instructions",
    "changelog": "Change log content",
    "download_link": "https://your-update-server.com/wp-json/secure-updates-server/v1/download/your-plugin-slug"
}
```

### 2. Download Endpoint
Uses `/wp-json/secure-updates-server/v1/download/{slug}` to fetch plugin updates

## Security Features

### SSL Verification
All requests enforce SSL verification:
```php
$args = [
    'timeout' => 15,
    'sslverify' => true,
];
```

### API Authentication
Optional Bearer token authentication:
```php
if (!empty($this->api_key)) {
    $args['headers'] = [
        'Authorization' => 'Bearer ' . $this->api_key,
    ];
}
```

### Input Sanitization
- URLs: `esc_url_raw()`
- Text: `sanitize_text_field()`
- HTML content: `wp_kses_post()`
- Slugs: `sanitize_title()`

## Debug Logging

When WP_DEBUG is enabled, the library logs:
- Update checks
- Version comparisons
- API communication errors
- Invalid responses

Example log message:
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[Secure Updates Library] Update available for ' . $this->plugin_slug . ': ' . $plugin_info->version);
}
```

## WordPress Integration

### Update Transient Filter
```php
add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
```

### Plugin Information Filter
```php
add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
```

## Example Implementation

```php
<?php
/*
Plugin Name: Your Amazing Plugin
Version: 1.0.0
*/

// Include the library
require_once plugin_dir_path(__FILE__) . 'includes/secure-updates-library.php';

if (class_exists('Secure_Updates_Library')) {
    // Initialize secure updates
    new Secure_Updates_Library(
        'https://your-update-server.com',
        '1.0.0',
        'your-api-key'
    );
}

// Rest of your plugin code
```

## Best Practices

1. **Version Management**
    - Use semantic versioning (e.g., 1.0.0)
    - Keep versions synchronized between plugin header and library initialization

2. **Server Configuration**
    - Use HTTPS for the update server
    - Configure appropriate caching headers
    - Implement rate limiting if needed

3. **Error Handling**
    - Enable WP_DEBUG during testing
    - Monitor error logs for update-related issues
    - Implement fallback mechanisms if update server is unreachable

4. **Security**
    - Keep API keys secure
    - Regularly rotate API keys
    - Use SSL for all communications
    - Validate and sanitize all data

## Limitations

1. Single update source per plugin
2. No support for plugin dependencies
3. Requires PHP 5.6 or higher
4. No automatic rollback functionality