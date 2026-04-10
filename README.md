# Laravel Postman Collection Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yourvendor/laravel-postman-generator.svg?style=flat-square)](https://packagist.org/packages/yourvendor/laravel-postman-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/yourvendor/laravel-postman-generator.svg?style=flat-square)](https://packagist.org/packages/yourvendor/laravel-postman-generator)
[![License](https://img.shields.io/packagist/l/yourvendor/laravel-postman-generator.svg?style=flat-square)](https://packagist.org/packages/yourvendor/laravel-postman-generator)

Generate Postman collections from your Laravel routes with **automatic Sanctum authentication support**, intelligent route grouping, and comprehensive API documentation.

## ✨ Features

- 🔐 **Automatic Sanctum/Bearer Token Handling** - Pre-request scripts automatically store and use authentication tokens
- 📁 **Multiple Grouping Strategies** - Group routes by prefix, controller, resource, route name, middleware, or PHPDoc tags
- 📝 **Full API Documentation** - Include validation rules, PHPDoc descriptions, middleware info, and example bodies
- 🔄 **Interactive CLI** - Beautiful terminal prompts guide you through the generation process
- 🌍 **Environment File Generation** - Automatically create Postman environment files with your variables
- 🎯 **Smart Route Detection** - Automatically identifies login/logout endpoints and authenticated routes
- 📦 **Laravel 10-13 Support** - Compatible with all modern Laravel versions

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, 12.x, or 13.x

## 🚀 Installation

Install the package via Composer:

```bash
composer require yourvendor/laravel-postman-generator --dev
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=postman-generator-config
```

## 📖 Usage

### Basic Usage

Generate a collection with interactive prompts:

```bash
php artisan postman:collection
```

### With Bearer Token Authentication (Sanctum)

```bash
php artisan postman:collection --Bearer
```

This will:
1. Add a collection-level pre-request script that automatically attaches the Bearer token
2. Add post-response scripts to login endpoints that store the token
3. Add post-response scripts to logout endpoints that clear the token
4. Set up proper authentication headers for all protected routes

### Command Options

```bash
php artisan postman:collection [options]

Options:
  --Bearer              Include Bearer token authentication with Sanctum pre-scripts
  --name=NAME           Collection name (default: your app name)
  --output=PATH         Output directory path
  --strategy=STRATEGY   Grouping strategy: prefix, controller, resource, name, middleware, tag
  --env                 Also generate environment file
  --full                Generate full descriptive collection with all documentation
  --minimal             Generate minimal collection without extra documentation
  --no-interaction      Skip interactive prompts and use defaults
```

### Examples

```bash
# Interactive mode with all prompts
php artisan postman:collection

# Quick generation with Bearer auth and environment file
php artisan postman:collection --Bearer --env

# Group by controller with custom name
php artisan postman:collection --strategy=controller --name="My API v2"

# Full documentation, non-interactive
php artisan postman:collection --Bearer --full --no-interaction

# Minimal collection for quick testing
php artisan postman:collection --minimal --no-interaction
```

## 🔐 Authentication Flow

When using the `--Bearer` flag, the package sets up automatic token management:

### 1. Collection-Level Pre-Request Script

Every request automatically checks for and attaches the stored token:

```javascript
const token = pm.environment.get("auth_token");
if (token) {
    pm.request.headers.upsert({
        key: "Authorization",
        value: "Bearer " + token
    });
}
```

### 2. Login Endpoint Post-Response Script

After successful login, the token is automatically stored:

```javascript
if (pm.response.code === 200 || pm.response.code === 201) {
    const response = pm.response.json();
    let token = response.token || response.access_token || response.data?.token;
    
    if (token) {
        pm.environment.set("auth_token", token);
        console.log("✓ Token stored successfully");
    }
}
```

### 3. Logout Endpoint Post-Response Script

After logout, the token is automatically cleared:

```javascript
if (pm.response.code === 200 || pm.response.code === 204) {
    pm.environment.unset("auth_token");
    console.log("✓ Token cleared successfully");
}
```

## 📁 Grouping Strategies

### 1. Prefix Based (default)
Groups routes by URL prefix:
```
/api/users/...     → "Users" folder
/api/posts/...     → "Posts" folder
/api/comments/...  → "Comments" folder
```

### 2. Controller Based
Groups routes by their controller class:
```
UserController     → "User" folder
PostController     → "Post" folder
AuthController     → "Auth" folder
```

### 3. Resource Based
Groups CRUD operations together with proper ordering:
```
Users              → index, store, show, update, destroy
Posts              → index, store, show, update, destroy
```

### 4. Route Name Based
Groups routes by their name prefix:
```
api.users.*        → "Users" folder
api.posts.*        → "Posts" folder
```

### 5. Middleware Based
Groups routes by their middleware:
```
auth:sanctum       → "Sanctum Protected Routes"
guest              → "Guest Only Routes"
(no middleware)    → "Public Routes"
```

### 6. PHPDoc Tag Based
Groups routes by `@tag` or `@group` annotations:

```php
/**
 * @tag Authentication
 */
class AuthController
{
    /**
     * @tag Users
     */
    public function login() {}
}
```

## ⚙️ Configuration

After publishing the config file, you can customize:

```php
// config/postman-generator.php

return [
    // Collection name and description
    'collection_name' => env('POSTMAN_COLLECTION_NAME', 'My API'),
    'description' => 'API Documentation',

    // Output location
    'output_path' => storage_path('postman'),

    // Base URL (use Postman variables)
    'base_url' => '{{base_url}}',

    // Authentication settings
    'auth' => [
        'type' => 'bearer',
        'token_variable' => 'auth_token',
        'login_routes' => ['login', 'auth.login'],
        'token_response_field' => 'token',
    ],

    // Route filtering
    'routes' => [
        'include' => ['api/*'],
        'exclude' => ['_ignition/*', 'sanctum/*', 'horizon/*'],
    ],

    // Documentation options
    'documentation' => [
        'include_phpdoc' => true,
        'include_validation_rules' => true,
        'include_examples' => true,
        'include_middleware' => true,
    ],

    // Default grouping strategy
    'grouping' => [
        'default' => 'prefix',
    ],
];
```

## 📤 Generated Files

### Collection File
`your-api.postman_collection.json`

Contains:
- All your API routes organized by chosen strategy
- Request headers (Accept, Content-Type)
- Authentication configuration
- Pre-request and post-response scripts
- Documentation and descriptions

### Environment File (optional)
`your-api.postman_environment.json`

Contains:
- `base_url` - Your API base URL
- `auth_token` - Stored authentication token
- `token_expiry` - Token expiration timestamp

## 🎨 Using in Postman

1. **Import Collection**: File → Import → Select the `.postman_collection.json` file
2. **Import Environment**: File → Import → Select the `.postman_environment.json` file
3. **Set Environment**: Select your environment from the dropdown
4. **Configure base_url**: Edit your environment and set the `base_url` variable
5. **Authenticate**: Call your login endpoint - the token will be stored automatically
6. **Make Requests**: All authenticated endpoints will now work automatically

## 🧪 Testing

```bash
composer test
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email your@email.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 💖 Credits

- [Your Name](https://github.com/yourusername)
- [All Contributors](../../contributors)
