# Secure Updates Library

**Version:** 1.2  
**Author:** Secure Updates Foundation  
**License:** GPLv2 or later

## Description

The **Secure Updates Library** offers WordPress plugin authors a secure and efficient way to manage plugin updates hosted on their own servers. By integrating this library into their plugins, authors can provide automatic updates directly from their secure mirror, mirroring the update functionality found on WordPress.org. This library not only simplifies the update process but also enhances security through API key authentication.

### Key Features

- **Self-hosted Updates**: Host plugin updates on your own secure server, eliminating reliance on third-party repositories.
- **Simple Integration**: Integrate the library into any plugin by including a single PHP file and initializing it with essential parameters.
- **Automatic Update Checks**: Automatically check for plugin updates and notify administrators when new versions are available.
- **API Key Authentication**: Securely authenticate update requests using API keys, ensuring that only authorized clients can access plugin updates.
- **Data Sanitization and Validation**: Ensures all data fetched from the server is properly sanitized to maintain security.
- **Extensible Admin Notices**: Placeholder for implementing custom admin notices based on update statuses.

## Installation and Implementation

### Step 1: Include the Library

1. **Download the Library**
    - Download the `secure-updates-library.php` file.

2. **Organize the Library**
    - Place the library file in your plugin's directory.
    - For better organization, create a subdirectory (e.g., `secure-updates`) within your plugin and place the library file there.

### Step 2: Configure the Secure Updates Server

1. **Install the Secure Updates Server**
    - Ensure that the [Secure Updates Server](https://github.com/secure-updates/secure-updates-server) is installed and properly configured on your server.
    - Generate an API key through the server's admin interface to be used for authenticating update requests.

2. **Manage API Keys**
    - Navigate to the **API Keys** section in the Secure Updates Server admin panel.
    - **Add a New API Key**: Generate a unique API key that will be used by your plugin to authenticate with the server.
    - **Store the API Key Securely**: Keep the API key confidential and avoid exposing it publicly.

### Step 3: Initialize the Library in Your Plugin

In your main plugin file, include and initialize the Secure Updates Library as follows:

```php
/*
Plugin Name: Your Plugin Name
Description: Description of your plugin.
Version: 1.2.3
Author: Your Name
Text Domain: your-plugin-text-domain
*/

// Ensure the secure updates array exists
if (!isset($secure_updates_instances) || !is_array($secure_updates_instances)) {
    $secure_updates_instances = [];
}

// Include the Secure Updates Library
include_once trailingslashit(plugin_dir_path(__FILE__)) . 'secure-updates/secure-updates-library.php';

// Initialize the Secure Updates Library with API key
$secure_updates_instances[] = new Secure_Updates_Library(
    'https://your-secure-updates-server.com', // Base URL of your secure updates server
    '1.2.3',                                   // Current version of your plugin
    'YOUR_API_KEY_HERE'                        // API Key for authenticating with the server
);
```

#### **Parameters Explained**

- **`https://your-secure-updates-server.com`**: The base URL of your secure updates server where plugin updates are hosted.
- **`1.2.3`**: The current version of your plugin. Update this version number with each new release.
- **`YOUR_API_KEY_HERE`**: The API key generated from the Secure Updates Server. This key authenticates your plugin with the server to securely fetch updates.

### Step 4: Host Plugin Update Files

Ensure that your secure updates server is configured to serve plugin updates. The library expects the following authenticated endpoints to be available on the mirror server:

- **Download Endpoint**: `/wp-json/plugin-server/v1/download/{plugin_slug}` — Used for downloading the latest plugin version.
- **Info Endpoint**: `/wp-json/plugin-server/v1/info/{plugin_slug}` — Provides detailed information about the plugin, including version, changelog, etc.

**Note:** These endpoints now require API key authentication. Ensure that your server-side implementation verifies the API key before processing requests.

## How It Works

- **Update Check**: The library hooks into WordPress's plugin update mechanism by using the `pre_set_site_transient_update_plugins` filter. It fetches the latest plugin version from the Secure Updates Server's Info Endpoint and compares it with the current version installed on the site.

- **Update Information**: The `plugins_api` filter is used to display detailed plugin information when a user clicks on the plugin details in the admin dashboard. This information is retrieved from the server's Info Endpoint.

## Security Enhancements

- **API Key Authentication**: All update requests to the Secure Updates Server must include a valid API key. This ensures that only authorized plugins can access update information and download packages.

- **Data Sanitization and Validation**: All data fetched from the server is sanitized and validated to prevent security vulnerabilities such as Cross-Site Scripting (XSS).

- **Secure REST API Endpoints**: The server's REST API endpoints are secured to require authentication, preventing unauthorized access and potential abuse.

## Frequently Asked Questions

### Can I use this library for multiple plugins?

Yes, you can use this library for multiple plugins. Simply create a separate instance of the `Secure_Updates_Library` class for each plugin, passing in the relevant parameters, including the API key for each one. Each instance should be added to the `$secure_updates_instances` array to avoid naming conflicts.

### Is the library compatible with all versions of WordPress?

The library is designed to work with WordPress 5.0 and above. It utilizes standard WordPress filters for update management, ensuring broad compatibility with recent WordPress versions.

### How do I generate and manage API keys?

API keys are managed through the Secure Updates Server's admin interface. Navigate to the **API Keys** section to generate new keys or revoke existing ones. Ensure that each plugin instance uses a unique API key for enhanced security.

### What should I do if update checks are failing?

- **Verify API Key**: Ensure that the API key provided during library initialization is correct and has the necessary permissions on the server.
- **Check Server Endpoints**: Confirm that the server's REST API endpoints (`/download/{plugin_slug}` and `/info/{plugin_slug}`) are accessible and properly secured.
- **Enable Debugging**: Turn on WordPress debugging (`WP_DEBUG`) to view error logs for any issues related to update checks.

## Changelog

### Version 1.2

- **Added API Key Authentication**: Introduced API key support for authenticating update requests, enhancing security.
- **Revised Update Checking Mechanism**: Updated the `check_for_updates` function to fetch and compare plugin versions from the Secure Updates Server instead of local plugin data.
- **Data Sanitization Enhancements**: Improved data sanitization and validation for all server responses to prevent security vulnerabilities.
- **Admin Notices Placeholder**: Added a placeholder for implementing custom admin notices based on update statuses.
- **Improved Documentation**: Updated installation and implementation instructions to reflect the new features and enhancements.

### Version 1.1

- Added automatic detection of the plugin slug from the plugin's directory name.
- Updated example usage to use a shared array (`$secure_updates_instances`) for storing library instances.

### Version 1.0

- Initial release.
- Supports automatic update checks for plugins hosted on a secure mirror server.
- Provides plugin information via custom REST API endpoints.

## License

This library is licensed under the GPLv2 or later. You can find the license [here](https://www.gnu.org/licenses/gpl-2.0.html).

