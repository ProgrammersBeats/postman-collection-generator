<p align="center">
  <img src="src/assets/images/banner.png" alt="Laravel Postman Collection Generator" width="100%">
</p>

<p align="center">
  <a href="https://packagist.org/packages/programmersbeats/postman-generator"><img src="https://img.shields.io/packagist/v/programmersbeats/postman-generator.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/programmersbeats/postman-generator"><img src="https://img.shields.io/packagist/dt/programmersbeats/postman-generator.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/programmersbeats/postman-generator"><img src="https://img.shields.io/packagist/l/programmersbeats/postman-generator.svg?style=flat-square" alt="License"></a>
</p>

<p align="center">
  The most advanced Laravel Postman collection generator.<br>
  <strong>Import and start testing immediately</strong> - zero manual configuration required.
</p>

## Why This Package?

Existing packages generate a JSON file and leave you to configure everything manually. This package generates a **production-ready collection** with auto-generated test scripts, realistic sample data from your factories, example API responses, and a beautiful browser documentation page - all working out of the box.

### What Makes This Different

| Feature | This Package | Others |
|---------|:---:|:---:|
| Auto-generated Postman **test scripts** per endpoint | Yes | No |
| Example **response bodies** from API Resources | Yes | No |
| Realistic request data from **Model Factories** | Yes | No |
| **Zero-config environment** (auto-detects APP_URL) | Yes | No |
| **API Changelog** command (`postman:diff`) | Yes | No |
| **Browser documentation** page with cURL copy | Yes | No |
| **Nested folder** structure from route prefixes | Yes | Partial |
| **cURL commands** generated per endpoint | Yes | No |
| **Rate limit** documentation from throttle middleware | Yes | No |
| 6 grouping strategies | Yes | 1-3 |
| Interactive CLI with Laravel Prompts | Yes | Partial |

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, 12.x, or 13.x

## Installation

```bash
composer require programmersbeats/postman-generator --dev
```

```bash
php artisan vendor:publish --tag=postman-generator-config
```

## Quick Start

```bash
# Generate a single collection file (ready to import)
php artisan postman:collection --Bearer -n
```

**That's it.** Import the single file into Postman and start testing. Everything is embedded - no separate environment file needed.

Output files are auto-versioned:
```
storage/postman/neorecruits-api-2026-v1.postman_collection.json   (1st run)
storage/postman/neorecruits-api-2026-v2.postman_collection.json   (2nd run)
storage/postman/neorecruits-api-2026-v3.postman_collection.json   (3rd run)
```

Then visit **`http://your-app.test/api-documentation`** to view your docs in the browser.

---

## Feature 1: Auto-Generated Test Scripts

Every endpoint in your collection gets **automatic Postman test scripts**. No other package does this.

Generated tests include:
- Status code validation (200, 201, 204 based on HTTP method)
- Response time check (under 2 seconds)
- Content-Type JSON validation
- Response structure validation (data array for index, data object for show)
- Validation error detection for POST/PUT/PATCH
- Auth token validity check for protected routes

Example test script auto-generated for a `GET /api/users` endpoint:

```javascript
pm.test('Status code is 200', function () {
    pm.response.to.have.status(200);
});

pm.test('Response time is acceptable', function () {
    pm.expect(pm.response.responseTime).to.be.below(2000);
});

pm.test('Response is valid JSON', function () {
    pm.response.to.be.json;
});

pm.test('Response has data array (paginated)', function () {
    const json = pm.response.json();
    if (json.data !== undefined) {
        pm.expect(json.data).to.be.an('array');
    }
});
```

Disable with `--no-tests` if not needed.

## Feature 2: Example Responses from API Resources

The package parses your `JsonResource` classes and generates **realistic example response bodies** embedded directly in the collection.

If your controller returns:
```php
public function show(User $user): UserResource
{
    return new UserResource($user);
}
```

And your `UserResource` has:
```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'created_at' => $this->created_at,
    ];
}
```

The collection will include an example response:
```json
{
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com",
        "created_at": "2026-01-15T10:30:00.000000Z"
    }
}
```

Disable with `--no-responses` if not needed.

## Feature 3: Realistic Request Data from Model Factories

Instead of generic `"sample_value"` placeholders, request bodies are populated with **realistic data parsed from your Model Factories**.

If your `UserFactory` has:
```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->safeEmail(),
        'password' => Hash::make('password'),
    ];
}
```

The POST body becomes:
```json
{
    "name": "John Doe",
    "email": "user@example.com",
    "password": "password123"
}
```

Faker methods are mapped to realistic sample values (100+ methods supported). Disable with `--no-factory`.

## Feature 4: Single File, Zero-Config

Everything is embedded in **one collection file** - no separate environment file needed. The collection includes your actual `APP_URL` as pre-configured variables:

- `{{base_url}}` - Auto-set to your `APP_URL/api`
- `{{Bearer}}` - Token stored automatically after login
- `{{token_expiry}}` - Token expiry tracking
- `{{app_url}}` - Your application URL

**Import one file -> Start testing.** No configuration steps.

## Feature 5: API Changelog (`postman:diff`)

Compare your current routes against the last generated collection to see what changed.

```bash
php artisan postman:diff
```

Output:
```
+ 3 New Endpoint(s):
  + [POST] api/auth/2fa/enable
  + [POST] api/auth/2fa/confirm
  + [GET]  api/auth/2fa/recovery-codes

- 1 Removed Endpoint(s):
  - [POST] api/auth/verify-email

~ 1 Modified Endpoint(s):
  ~ [PUT] api/users/{user}
      Now requires authentication

Summary:
  Added:    3
  Removed:  1
  Modified: 1
  Total:    24 endpoints
```

Save as markdown report:
```bash
php artisan postman:diff --output=CHANGELOG-api.md
```

## Feature 6: Browser API Documentation

Visit `/api-documentation` for a beautiful, interactive documentation page.

**Page features:**
- Dark sidebar with collapsible nested folder navigation
- Color-coded HTTP method badges (GET, POST, PUT, PATCH, DELETE)
- Real-time search across all endpoints (press `/` to focus)
- Expandable route cards with full details
- **Copy-as-cURL button** for every endpoint
- **Example response display** inline
- Rate limit information from throttle middleware
- Auth/public route indicators
- Responsive + print support

## Feature 7: Nested Folder Structure

Route prefixes create hierarchical Postman folders:

```php
Route::prefix('auth/pin')->group(function () {
    Route::post('set', [PinController::class, 'setPin']);
    Route::put('update', [PinController::class, 'updatePin']);
});

Route::prefix('auth/2fa')->group(function () {
    Route::post('enable', [TwoFactorSetupController::class, 'enable']);
    Route::post('confirm', [TwoFactorSetupController::class, 'confirm']);
});
```

Produces:
```
Auth/
  Pin/
    Set Pin
    Update Pin
  2fa/
    Enable
    Confirm
```

Customize folder names:
```php
'grouping' => [
    'folder_names' => [
        'auth'      => 'Authentication',
        'auth/pin'  => 'PIN Management',
        'auth/2fa'  => '2FA Setup',
    ],
],
```

## Feature 8: Multi-Route-File Support

Automatically discovers routes from all registered files. Works with Laravel 11's `bootstrap/app.php`:

```php
->withRouting(
    api: __DIR__.'/../routes/api.php',
    then: function () {
        Route::middleware('api')->prefix('api')
            ->group(base_path('routes/candidate.php'));
        Route::middleware('api')->prefix('api')
            ->group(base_path('routes/admin.php'));
    },
)
```

All routes from every file appear in the collection and documentation.

## Feature 9: cURL Commands

Every endpoint includes a ready-to-use cURL command in both the Postman collection description and the browser documentation:

```bash
curl -X POST \
  'http://your-app.test/api/auth/login' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{}'
```

## Feature 10: Rate Limit Documentation

Throttle middleware is automatically parsed and displayed:

```php
Route::middleware('throttle:60,1')->group(function () {
    // Routes here show "60 requests per 1 minute(s)" in docs
});
```

## Command Flags

### `php artisan postman:collection`

| Flag | Description |
|------|-------------|
| `--Bearer` | Include Bearer token authentication with Sanctum pre-scripts |
| `--name=NAME` | Set the collection name (default: your app name) |
| `--output=PATH` | Set the output directory (default: `storage/postman`) |
| `--strategy=STRATEGY` | Grouping strategy: `prefix`, `controller`, `resource`, `name`, `middleware`, `tag` |
| `--full` | Full documentation mode with all details |
| `--minimal` | Minimal mode - just routes, no extra documentation |
| `--no-tests` | Disable auto-generated Postman test scripts |
| `--no-responses` | Disable example response generation from API Resources |
| `--no-factory` | Disable factory-based realistic sample data |
| `-n` | Non-interactive mode, skip prompts and use defaults |

### `php artisan postman:diff`

| Flag | Description |
|------|-------------|
| `--collection=PATH` | Path to existing collection file to compare against |
| `--output=PATH` | Save the diff report as a markdown file |

### Examples

```bash
# Full collection with Bearer auth (non-interactive)
php artisan postman:collection --Bearer -n

# Interactive mode - guided setup with prompts
php artisan postman:collection

# Custom name and controller grouping
php artisan postman:collection --Bearer --name="My API v2" --strategy=controller -n

# Minimal collection without test scripts
php artisan postman:collection --Bearer --minimal --no-tests -n

# Compare routes with last generated collection
php artisan postman:diff

# Save API changelog as markdown
php artisan postman:diff --output=API-CHANGELOG.md
```

## Grouping Strategies

| Strategy | Description |
|----------|-------------|
| `prefix` (default) | Nested folders from URL prefixes |
| `controller` | Group by controller class |
| `resource` | CRUD operations grouped with proper ordering |
| `name` | Group by route name prefix |
| `middleware` | Group by middleware (auth, guest, etc.) |
| `tag` | Group by PHPDoc `@tag` or `@group` annotations |

## Configuration

```php
// config/postman-generator.php

'features' => [
    'test_scripts' => true,        // Auto-generate Postman test scripts
    'example_responses' => true,    // Generate example responses from Resources
    'factory_data' => true,         // Use Factory data for request bodies
],

'documentation' => [
    'enabled' => true,
    'route' => 'api-documentation',
    'middleware' => ['web'],
],

'grouping' => [
    'default' => 'prefix',
    'folder_names' => [
        // 'auth/pin' => 'PIN Management',
    ],
],
```

## Publishing & Customization

```bash
php artisan vendor:publish --tag=postman-generator-config
php artisan vendor:publish --tag=postman-generator-views
php artisan vendor:publish --tag=postman-generator-stubs
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover any security-related issues, please email programmersbeats@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Ameer Hamza](https://github.com/ProgrammersBeats)
- [All Contributors](../../contributors)

## Support

If you find this package helpful, consider supporting [ProgrammersBeats](https://github.com/ProgrammersBeats) by starring the repository and sharing it with your network.
