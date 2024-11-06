# Secure Updates Library
## Version 2.0

## Description

The **Secure Updates Library** offers WordPress plugin authors a secure and efficient way to manage plugin updates hosted on their own servers. By integrating this library into their plugins, authors can provide automatic updates directly from their secure mirror, mirroring the update functionality found on WordPress.org. This library not only simplifies the update process but also enhances security through API key authentication.

## Features

### Core Functionality
- 🔐 Secure plugin updates via private servers
- 🔑 API key authentication
- 🔄 Automatic version checking
- 📦 WordPress update system integration

### New in 2.0
- ✅ Package integrity verification
- 💾 Automatic backup and rollback
- 📊 Enhanced logging system
- 🏥 Health monitoring
- ⚡ Rate limiting protection
- 🧪 Test mode for server verification

## Requirements

- WordPress 5.0 or higher
- PHP 5.6 or higher
- OpenSSL PHP extension
- Write permissions for plugin directory

## Installation

1. Download the library files
2. Place them in your plugin's directory:
```
your-plugin/
├── secure-updates/
│   └── secure-updates-library.php
├── your-plugin.php
└── [...other files]
```

## Quick Start

### Basic Implementation
```php
/**
 * Initialize Secure Updates Library
 */
if (!isset($secure_updates_instances) || !is_array($secure_updates_instances)) {
    $secure_updates_instances = [];
}

include_once trailingslashit(plugin_dir_path(__FILE__)) . 'secure-updates/secure-updates-library.php';

$secure_updates_instances[] = new Secure_Updates_Library(
    'https://your-update-server.com',  // Your update server URL
    '1.0.0',                          // Your plugin version
    'your-api-key'                    // Your API key
);
```

### Advanced Implementation
```php
$secure_updates_instances[] = new Secure_Updates_Library(
    'https://your-update-server.com',
    '1.0.0',
    'your-api-key',
    false, // Test mode
    [
        'verify_packages' => true,
        'enable_logging' => true,
        'health_monitoring' => true,
        'rate_limiting' => [
            'requests_per_minute' => 30
        ]
    ]
);
```

## Configuration Options

### Test Mode
```php
// Enable test mode to verify server connection
$test_mode = true;
```

### Package Verification
```php
$options['verify_packages'] = true; // Enable package verification
```

### Rate Limiting
```php
$options['rate_limiting'] = [
    'requests_per_minute' => 30,
    'burst' => 5
];
```

### Logging
```php
$options['enable_logging'] = true;
$options['log_level'] = 'debug'; // error, warning, info, debug
```

## Server Requirements

Your update server must implement these endpoints:
- `/wp-json/secure-updates-server/v1/info/{slug}`
- `/wp-json/secure-updates-server/v1/download/{slug}`
- `/wp-json/secure-updates-server/v1/verify_file/{slug}`
- `/wp-json/secure-updates-server/v1/connected`

## Security Features

- Package checksum verification
- API key authentication
- SSL/TLS requirement
- Rate limiting protection
- Pre-update backups
- File integrity checks

## Health Monitoring

The library integrates with WordPress Site Health and provides:
- System compatibility checks
- Server connection monitoring
- Update system diagnostics
- SSL/TLS verification
- File permission checks

## Logging System

Comprehensive logging with:
- Multiple log levels
- Context information
- Log rotation
- Admin interface integration
- Debug.log integration

## Development

### Debugging
Enable debug mode in wp-config.php:
```php
define('WP_DEBUG', true);
define('SUP_DEBUG', true);
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Support

- Documentation: [Link to docs]
- Issues: [GitHub Issues]
- Wiki: [GitHub Wiki]

## License

GPL-2.0 or later. See [LICENSE](LICENSE) for details.