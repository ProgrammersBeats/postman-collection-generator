# Laravel Postman Collection Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/programmersbeats/postman-generator.svg?style=flat-square)](https://packagist.org/packages/programmersbeats/postman-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/programmersbeats/postman-generator.svg?style=flat-square)](https://packagist.org/packages/programmersbeats/postman-generator)
[![License](https://img.shields.io/packagist/l/programmersbeats/postman-generator.svg?style=flat-square)](https://packagist.org/packages/programmersbeats/postman-generator)

Generate beautiful Postman collections from your Laravel routes with **nested folder structure**, **browser-based API documentation**, **automatic Sanctum authentication**, and intelligent route grouping.

## Features

- **Nested Folder Structure** - Routes grouped by prefix create hierarchical folders (e.g., `Auth > PIN > Set Pin`)
- **Browser API Documentation** - Visit `/api-documentation` to view a beautiful, searchable documentation page
- **Multi-Route-File Support** - Automatically discovers routes from all registered files (`api.php`, `candidate.php`, etc.)
- **Automatic Sanctum/Bearer Token Handling** - Pre-request scripts automatically store and use authentication tokens
- **Multiple Grouping Strategies** - Group routes by prefix, controller, resource, route name, middleware, or PHPDoc tags
- **Full API Documentation** - Include validation rules, PHPDoc descriptions, middleware info, and example bodies
- **Interactive CLI** - Beautiful terminal prompts guide you through the generation process
- **Environment File Generation** - Automatically create Postman environment files with your variables
- **Smart Route Detection** - Automatically identifies login/logout endpoints and authenticated routes
- **Custom Folder Names** - Configure display names for any folder via config
- **Laravel 10-13 Support** - Compatible with all modern Laravel versions

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, 12.x, or 13.x

## Installation

Install the package via Composer:

```bash
composer require programmersbeats/postman-generator --dev
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=postman-generator-config
```

Publish the views for customization (optional):

```bash
php artisan vendor:publish --tag=postman-generator-views
```

## Quick Start

```bash
# Interactive mode - guided setup
php artisan postman:collection

# Quick generation with Bearer auth
php artisan postman:collection --Bearer --env

# Non-interactive with all defaults
php artisan postman:collection --Bearer --full --env --no-interaction
```

Then visit **`http://your-app.test/api-documentation`** to view your API documentation in the browser.

## Browser API Documentation

The package automatically registers a route at `/api-documentation` that displays a beautiful, searchable documentation page for your API.

**Features of the documentation page:**

- Collapsible sidebar with nested folder navigation
- Color-coded HTTP method badges (GET, POST, PUT, PATCH, DELETE)
- Real-time search across all endpoints (press `/` to focus)
- Expandable route cards showing controller, middleware, parameters, and validation rules
- Auth/public route indicators
- Responsive design with print support

### Configuration

```php
// config/postman-generator.php

'documentation' => [
    'enabled' => true,                    // Enable/disable the route
    'route' => 'api-documentation',       // URL path
    'middleware' => ['web'],              // Add 'auth' to restrict access
    'title' => null,                      // Custom title (defaults to collection_name)
],
```

To disable the documentation route:

```php
'documentation' => [
    'enabled' => false,
],
```

## Nested Folder Structure

The prefix grouping strategy (default) creates **nested folders** based on your route prefixes. This produces a clean, hierarchical collection structure in Postman.

### Example

Given these routes:

```php
// routes/api.php

// PIN Management
Route::prefix('auth/pin')->group(function () {
    Route::post('set', [PinController::class, 'setPin'])->name('auth.pin.set');
    Route::put('update', [PinController::class, 'updatePin'])->name('auth.pin.update');
    Route::post('verify', [PinController::class, 'verifyPin'])->name('auth.pin.verify');
    Route::post('disable', [PinController::class, 'disablePin'])->name('auth.pin.disable');
    Route::get('status', [PinController::class, 'getStatus'])->name('auth.pin.status');
});

// 2FA Setup
Route::prefix('auth/2fa')->group(function () {
    Route::post('enable', [TwoFactorSetupController::class, 'enable'])->name('auth.2fa.enable');
    Route::post('confirm', [TwoFactorSetupController::class, 'confirm'])->name('auth.2fa.confirm');
    Route::post('disable', [TwoFactorSetupController::class, 'disable'])->name('auth.2fa.disable');
    Route::get('recovery-codes', [TwoFactorSetupController::class, 'recoveryCodes'])->name('auth.2fa.recovery-codes');
    Route::post('recovery-codes/regenerate', [TwoFactorSetupController::class, 'regenerateRecoveryCodes'])->name('auth.2fa.recovery-codes.regenerate');
});

Route::apiResource('users', UserController::class);
```

The generated Postman collection will have this structure:

```
Auth/
  Pin/
    Set Pin
    Update Pin
    Verify Pin
    Disable Pin
    Get Status
  2fa/
    Enable
    Confirm
    Disable
    Recovery Codes
    Regenerate Recovery Codes
Users/
  List Users
  Create User
  Show User
  Update User
  Delete User
```

### Custom Folder Names

Override the default folder names via config:

```php
// config/postman-generator.php

'grouping' => [
    'folder_names' => [
        'auth'      => 'Authentication',
        'auth/pin'  => 'PIN Management',
        'auth/2fa'  => '2FA Setup',
        'users'     => 'User Management',
    ],
],
```

This produces:

```
Authentication/
  PIN Management/
    Set Pin, Update Pin, ...
  2FA Setup/
    Enable, Confirm, ...
User Management/
  List Users, ...
```

## Multi-Route-File Support

The package automatically discovers routes from **all registered route files**, not just `api.php`. This works seamlessly with Laravel 11's `bootstrap/app.php` routing.

### Laravel 11+ (bootstrap/app.php)

```php
// bootstrap/app.php

->withRouting(
    api: __DIR__.'/../routes/api.php',
    web: __DIR__.'/../routes/web.php',
    then: function () {
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/candidate.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/admin.php'));
    },
)
```

All routes from `api.php`, `candidate.php`, and `admin.php` will be included in the collection and documentation automatically.

### Laravel 10 (RouteServiceProvider)

```php
// app/Providers/RouteServiceProvider.php

Route::middleware('api')
    ->prefix('api')
    ->group(base_path('routes/api.php'));

Route::middleware('api')
    ->prefix('api')
    ->group(base_path('routes/candidate.php'));
```

## Authentication Flow

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
        console.log("Token stored successfully");
    }
}
```

### 3. Logout Endpoint Post-Response Script

After logout, the token is automatically cleared:

```javascript
if (pm.response.code === 200 || pm.response.code === 204) {
    pm.environment.unset("auth_token");
    console.log("Token cleared successfully");
}
```

## Grouping Strategies

### 1. Prefix Based (default)

Groups routes by URL prefix with **nested folders**:

```
/api/auth/pin/...  -> Auth > Pin
/api/auth/2fa/...  -> Auth > 2fa
/api/users/...     -> Users
/api/posts/...     -> Posts
```

### 2. Controller Based

Groups routes by their controller class:

```
UserController     -> "User" folder
PostController     -> "Post" folder
AuthController     -> "Auth" folder
```

### 3. Resource Based

Groups CRUD operations together with proper ordering:

```
Users              -> index, store, show, update, destroy
Posts              -> index, store, show, update, destroy
```

### 4. Route Name Based

Groups routes by their name prefix:

```
api.users.*        -> "Users" folder
api.posts.*        -> "Posts" folder
```

### 5. Middleware Based

Groups routes by their middleware:

```
auth:sanctum       -> "Sanctum Protected Routes"
guest              -> "Guest Only Routes"
(no middleware)    -> "Public Routes"
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

## Command Options

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
php artisan postman:collection --Bearer --full --env --no-interaction

# Minimal collection for quick testing
php artisan postman:collection --minimal --no-interaction
```

## Configuration

After publishing the config file, you can customize all aspects:

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

    // Route filtering (routes from ALL files are auto-discovered)
    'routes' => [
        'include' => ['api/*'],
        'exclude' => ['_ignition/*', 'sanctum/*', 'horizon/*'],
    ],

    // Browser API documentation
    'documentation' => [
        'enabled' => true,
        'route' => 'api-documentation',
        'middleware' => ['web'],
    ],

    // Grouping strategy and custom folder names
    'grouping' => [
        'default' => 'prefix',
        'folder_names' => [
            // 'auth/pin' => 'PIN Management',
            // 'auth/2fa' => '2FA Setup',
        ],
    ],
];
```

## Generated Files

### Collection File

`your-api.postman_collection.json`

Contains:
- All your API routes organized with nested folders
- Request headers (Accept, Content-Type)
- Authentication configuration
- Pre-request and post-response scripts
- Documentation and descriptions
- Sample request bodies from validation rules

### Environment File (optional)

`your-api.postman_environment.json`

Contains:
- `base_url` - Your API base URL
- `auth_token` - Stored authentication token
- `token_expiry` - Token expiration timestamp

## Using in Postman

1. **Import Collection**: File > Import > Select the `.postman_collection.json` file
2. **Import Environment**: File > Import > Select the `.postman_environment.json` file
3. **Set Environment**: Select your environment from the dropdown
4. **Configure base_url**: Edit your environment and set the `base_url` variable
5. **Authenticate**: Call your login endpoint - the token will be stored automatically
6. **Make Requests**: All authenticated endpoints will now work automatically

## Publishing & Customization

```bash
# Publish config
php artisan vendor:publish --tag=postman-generator-config

# Publish views (customize the documentation page)
php artisan vendor:publish --tag=postman-generator-views

# Publish script stubs (customize pre-request/post-response scripts)
php artisan vendor:publish --tag=postman-generator-stubs
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Security

If you discover any security-related issues, please email programmersbeats@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Ameer Hamza](https://github.com/ProgrammersBeats)
- [All Contributors](../../contributors)

## Support

If you find this package helpful, consider supporting [ProgrammersBeats](https://github.com/ProgrammersBeats) by starring the repository and sharing it with your network.
