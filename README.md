# Azelya SMS Service

PHP SDK for the **Azelya SMS Gateway API**. Send SMS, poll delivery status, register
devices, manage users, and authenticate — from any PHP application.

- **Framework-agnostic core**: works in plain PHP, Symfony, WordPress, anything with Composer.
- **Laravel integration**: auto-discovered service provider, `AzelyaSms` facade, published config.
- **Full API coverage**: auth (user + app), SMS, devices, admin, and gateway device endpoints.
- **Zero-config auth**: provide credentials once — the client logs in, caches the token,
  and re-authenticates transparently when it expires.
- Requires **PHP 8.2+** and **Guzzle 7.9+**.

## Installation

```bash
composer require azelya/sms-service
```

For local development against a path checkout:

```json
{
    "repositories": [
        { "type": "path", "url": "../azelya-sms-service" }
    ],
    "require": {
        "azelya/sms-service": "dev-main"
    }
}
```

## Quick start

### Plain PHP

```php
use Azelya\SmsService\SmsGatewayClient;
use Azelya\SmsService\Exception\SmsGatewayException;

$sms = new SmsGatewayClient([
    'base_url' => 'https://smsgate.test/api/v1',
    'email'    => 'client@example.com',   // auto-login credentials
    'password' => 'secret',
]);

// Send (queued asynchronously by the gateway)
$ack = $sms->sms()->send('+40721234567', 'Hello from the SDK!');
echo $ack->smsLogId;

// Wait until the gateway reports a final status
$log = $sms->sms()->waitForStatus($ack->smsLogId, timeoutSeconds: 60);
echo $log->status; // pending | sent | delivered | failed
```

If you already have a token, skip auto-login by passing `'token' => '...'` instead of credentials.

### Laravel

Publish the config and set your environment:

```bash
php artisan vendor:publish --tag=azelya-sms
```

```env
AZELYA_SMS_BASE_URL=https://smsgate.test/api/v1
AZELYA_SMS_EMAIL=client@example.com
AZELYA_SMS_PASSWORD=secret
```

```php
use Azelya\SmsService\Laravel\Facades\AzelyaSms;

$ack = AzelyaSms::sms()->send('+40721234567', 'Hello from Laravel!');
```

The client is bound as a singleton and injectable via `SmsGatewayClient::class`.
Tokens are cached in the Laravel cache repository.

## Reference

The client exposes one service per API area:

| Accessor | Endpoints |
|---|---|
| `$sms->auth()` | `/register`, `/login`, `/logout`, `/app/register`, `/app/login`, `/app/logout` |
| `$sms->user()` | `/user` |
| `$sms->sms()` | `/sms` (list), `/sms/send`, `/sms/{id}`, `/sms/{id}/conversation`, `/sms/{id}/retry` |
| `$sms->devices()` | `/user/devices` (list/create/get/delete) |
| `$sms->deviceGateway()` | `/device/broadcasting/auth`, `/device/reply`, `/device/status` |
| `$sms->admin()` | `/admin/users` (list/approve/revoke), `/admin/devices` (list/toggle) |

### Auth

```php
$response = $sms->auth()->login('client@example.com', 'secret');   // stores the token
$response->accessToken;                                            // Passport token (12-month expiry)
$response->user->name;

$sms->auth()->register('John Doe', 'john@example.com', 'Secret123'); // user has NO role yet
$sms->auth()->appRegister('My App', 'app@example.com', 'Secret123'); // AppClient, can send immediately
$sms->auth()->logout();                                             // revokes + forgets the token
```

> New users via `/register` get no role — they receive `PermissionDeniedException` (403) on
> SMS endpoints until an admin approves them (`$sms->admin()->approve($userId)`).
> AppClients via `/app/register` can send SMS right away.

### SMS

```php
$ack = $sms->sms()->send('+40721234567', 'Hello', deviceType: 'android'); // or DeviceType::Android
$ack->smsLogId; // poll with this id

$log = $sms->sms()->get($ack->smsLogId);   // SmsLog DTO (no nested device)
$log->status;                              // pending | sent | delivered | failed
$log->isPending(); $log->isFinal();

$sms->sms()->retry($ack->smsLogId);        // only works when status is "failed"

// List all messages (newest first), each with the device that handled it
$page = $sms->sms()->list(['per_page' => 25]);
foreach ($page->sms as $log) {
    echo $log->device?->name;              // SmsDevice {id, name, type} | null
}
$page->total(); $page->nextPage();

// A sent message together with its replies (matched by external_id)
$conversation = $sms->sms()->conversation($ack->smsLogId);
$conversation->sms;                        // SmsLog (with device)
$conversation->replies;                    // SmsLog[] (oldest first)
```

> `send()` throws `ValidationException` (errors under `device_type`) when the user has
> no active device — register one with `devices()->create()` or activate one via
> `admin()->setDeviceActive()`.

### Async delivery & polling

Sends are asynchronous (202). `sendAndWait()` and `waitForStatus()` poll
`GET /sms/{id}` until the status leaves `pending`:

```php
$log = $sms->sms()->sendAndWait(
    phone: '+40721234567',
    message: 'Hello',
    timeoutSeconds: 120,       // throws SmsTimeoutException on expiry
    pollIntervalSeconds: 2.0,
    onStatusChange: function (SmsLog $log) {
        echo "Now: {$log->status}\n";
    },
);
```

The wait ends at the **first non-pending status** — `sent`, `delivered`, or `failed`.
`sent` means the device accepted the message; `delivered` may or may not follow.

### Devices

A user can register multiple devices; each one gets its own token. Device types are
free-form `alpha_dash` strings of at least 3 characters (`android`, `ios`,
`galaxy-s22`, ...) — `DeviceType::Android`/`DeviceType::Iot` are conveniences.

```php
$devices = $sms->devices()->list();        // Device[] (not paginated)
$device = $sms->devices()->create('Pixel 8', 'android'); // always creates a NEW device
$device->token;                            // send this to the device as its X-Device-Token

$device = $sms->devices()->get($device->id);   // NotFoundException when unknown
$sms->devices()->delete($device->id);
```

> `get()`/`delete()` throw `PermissionDeniedException` for another user's device.
> All device endpoints require the `send-sms` permission — freshly `/register`ed
> users get 403 until approved.

### Admin

```php
$page = $sms->admin()->listUsers(['sort' => '-created_at', 'per_page' => 50]);
$page->users;               // User[]
$page->total(); $page->nextPage();

$user = $sms->admin()->approve($userId);   // assigns Client role
$user = $sms->admin()->revoke($userId);    // removes all roles

// Device management (includes unlinked gateway devices)
$page = $sms->admin()->listDevices(['sort' => 'name', 'per_page' => 25]); // Device[]
$device = $sms->admin()->setDeviceActive($deviceId, false); // deactivated devices never receive SMS
```

### Gateway devices (the device side)

Configure a static device token (`'device_token' => '...'` or `AZELYA_SMS_DEVICE_TOKEN`)
and call the endpoints the Android/IoT devices use — these authenticate with the
`X-Device-Token` header, not a bearer token:

```php
$auth = $sms->deviceGateway()->authorizeChannel($socketId, 'private-sms.android.'.$userId);
$auth->key();        // websocket app key
$auth->signature();  // HMAC signature for the subscription

$sms->deviceGateway()->updateStatus($smsLogId, 'delivered', externalId: 'modem-42');
$sms->deviceGateway()->reply('sms.reply', '+40721234567', 'Thanks!');
```

## Error handling

Every failure maps to a typed exception — all extend `SmsGatewayException`:

| Exception | HTTP | Meaning |
|---|---|---|
| `AuthenticationException` | 401 | Invalid credentials or device token |
| `PermissionDeniedException` | 403 | Missing role/permission, or another user's SMS |
| `NotFoundException` | 404 | Unknown SMS log, no device registered |
| `ConflictException` | 409 | Approving an approved user, revoking without roles |
| `ValidationException` | 422 | Field errors — use `$e->errors()` / `$e->errorsFor('phone')` |
| `RateLimitException` | 429 | Throttled — use `$e->retryAfter()` |
| `NetworkException` | — | Connection refused / timeout |
| `InvalidResponseException` | — | Gateway returned an unexpected body |
| `SmsTimeoutException` | — | Poller timeout — use `$e->lastStatus()` |

```php
try {
    $sms->sms()->send('+40721234567', 'Hello');
} catch (ValidationException $e) {
    foreach ($e->errors() as $field => $messages) {
        // ...
    }
} catch (RateLimitException $e) {
    sleep($e->retryAfter() ?? 60);
}
```

## Token lifecycle

- Passport tokens live **12 months**; there is no refresh endpoint.
- The client caches tokens (`FileTokenStore` by default, Laravel cache in Laravel apps,
  `ArrayTokenStore` for workers) and auto-login happens at most once per process.
- On a 401 the client re-logs-in and retries the request **once** (configurable via
  `retry_on_unauthorized`). This only applies to bearer requests with configured
  credentials — bad logins and device tokens never loop.
- `$sms->setToken($token)` / `$sms->forgetToken()` / `$sms->getToken()` for manual control.

## Known API limits

- **Sending requires an active device** — `POST /sms/send` returns 422
  (`ValidationException`, errors under `device_type`) when no active device can deliver.
- **Two pagination formats**: `/sms` returns a plain Laravel paginator while `/admin/*`
  collections are JSON:API — both expose the same `total()/currentPage()/lastPage()/
  perPage()/nextPage()/prevPage()` helpers.
- **Throttles**: register 6/min, SMS endpoints 30/min (list shares the bucket). 429s
  surface as `RateLimitException`; the SDK never auto-retries them.
- `/register` users cannot send SMS or manage devices until an admin approves them (403).

## Upgrading from the singular device API

The gateway replaced `/user/device` with multi-device `/user/devices` CRUD. In this SDK:
`devices()->get()` → `devices()->get($deviceId)`, `devices()->delete()` →
`devices()->delete($deviceId)`, plus the new `devices()->list()`.

## Testing

```bash
composer install
vendor/bin/phpunit
```

All tests run offline against mocked HTTP responses; no live gateway is required.
