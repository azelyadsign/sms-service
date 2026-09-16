# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**azelya/sms-service** ("Azelya SMS Service") — a Composer SDK that lets any PHP application consume the Azelya SMS Gateway API (`C:\laragon\www\smsgate`, Laravel 13 REST API at `/api/v1`). Sibling project, not a dependency of it.

**Design decisions (user-approved, keep them):**
- **Framework-agnostic core**: plain PHP + Guzzle, usable in any PHP app. No Illuminate imports outside `src/Laravel/`.
- **Optional Laravel bridge**: auto-discovered `SmsServiceProvider`, `AzelyaSms` facade, publishable config (`azelya-sms` key) — wired via `extra.laravel` in composer.json.
- **Full API coverage**: auth (user + app variants), SMS, user devices, admin, gateway-device endpoints.

**Stack:** PHP ^8.2 (dev machine 8.4), Guzzle ^7.9, PHPUnit ^11. PSR-4: `Azelya\SmsService\` → `src/`, `Azelya\SmsService\Tests\` → `tests/`.

## Commands

```bash
composer install                       # deps (guzzle, phpunit)
vendor/bin/phpunit                     # full suite (offline, Guzzle MockHandler)
vendor/bin/phpunit --filter=TestName   # single test
composer validate --strict             # validate composer.json
find src tests -name '*.php' -exec php -l {} \;   # syntax check all files
```

There is no pint/Laravel in this package — keep code PSR-12 clean manually.

## Architecture

### Entry point — `src/SmsGatewayClient.php`

```php
$sms = new SmsGatewayClient(Config|array $config, ?TokenStore, ?ClientInterface $http);
$sms->auth();          // AuthService      — register/login/logout + appRegister/appLogin/appLogout
$sms->user();          // UserService      — me()
$sms->sms();           // SmsService       — send/get/retry/sendAndWait/waitForStatus
$sms->devices();       // UserDeviceService     — user device CRUD (bearer)
$sms->deviceGateway(); // DeviceGatewayService  — broadcasting auth, reply, status (X-Device-Token)
$sms->admin();         // AdminService     — listUsers/approve/revoke
$sms->getToken() / setToken() / forgetToken();
```

- **`src/Http/ApiClient.php`** is the correctness core — the ONLY place that talks HTTP: URI joining, JSON decode, status→exception mapping, auth modes, auto-login, 401-retry. Services only build payloads and map DTOs.
- **`src/Http/AuthMode.php`** — `None` (login/register), `Bearer` (API consumers), `Device` (`X-Device-Token` header for gateway device endpoints).
- **`src/Config.php`** — immutable; `fromArray()`/`toArray()` use snake_case keys (matches the Laravel config). `credentials()`: app pair takes precedence over user pair.
- **`src/TokenStore/`** — `FileTokenStore` (default, temp dir), `ArrayTokenStore` (workers), plus `src/Laravel/CacheTokenStore`.
- **`src/Dto/`** — readonly DTOs (`User`, `AuthResponse`, `SmsLog`, `Device`, `DeviceAuth`, `DeliveryAck`, `PaginatedUsers`). Dates stay raw ISO strings. `SmsLog::isPending()/isFinal()`; flat JSON from `GET /sms/{id}` vs JSON:API `{data: {id, type, attributes}}` elsewhere.
- **`src/Exception/`** — all extend `SmsGatewayException` (`statusCode()`, `context`): 401→`AuthenticationException`, 403→`PermissionDeniedException`, 404→`NotFoundException`, 409→`ConflictException`, 422→`ValidationException` (has `errors()`/`errorsFor()`), 429→`RateLimitException` (`retryAfter()`), plus `NetworkException`, `InvalidResponseException`, `SmsTimeoutException`.
- **`src/Support/SmsStatusPoller.php`** — polls until status != `pending`. **Stops at the first non-pending status** (`sent` is final — mirrors the gateway job's own semantics). Units: deadline is `hrtime()` **nanoseconds**, `usleep()` takes **microseconds** — a previous nano/micro mixup made polls sleep 10s; keep `MICROS_PER_SECOND` for the interval.
- **`src/Laravel/`** — `SmsServiceProvider` (singleton `SmsGatewayClient`, merges/publishes `config/azelya-sms.php`, `CacheTokenStore`), `Facades\AzelyaSms`. Only loaded when Laravel runs.

### Key behavior: auth & retry

- Auto-login: on first bearer request without a token, login with configured credentials and cache the token (store → memory → config precedence).
- 401-retry: a bearer 401 with credentials configured triggers forget-token → re-login → retry the **exact request once** (`retryOnUnauthorized`, default on). Never applies to `AuthMode::None`/`Device` calls — no loops on bad credentials.
- `auth()->register()` does NOT store the token; `login()`/`appLogin()` do. Logout revokes + forgets.

### Gateway API quirks the SDK handles (verified against smsgate source)

- `POST /user/device` returns a **top-level array** `[{data: {...}}]` — `UserDeviceService::extractDevice()` unwraps defensively.
- `GET /sms/{id}` returns **flat JSON**, not JSON:API — `SmsLog::fromArray()` takes the body directly.
- Gateway throttles: register 6/min, SMS 30/min → 429s surface as `RateLimitException`, never auto-retried.
- New `/register` users have no role → 403 on SMS until `admin()->approve()`; `/app/register` (AppClient) can send immediately.
- smsgate's own feature tests are partially stale (android→device rename, PATCH admin routes) — trust `routes/api.php` + controllers in the smsgate repo, not its tests.

## Testing conventions

All tests are offline via Guzzle `MockHandler` — see `tests/TestCase.php` for the shared helpers:

- `$this->queue->append($this->jsonResponse(...))` — FIFO mock responses; **queue must not run dry** (empty queue throws).
- `$this->client(Config, ?TokenStore)` — builds a client wired with `Middleware::history($this->history)`; use `$this->lastRequest()` / `$this->requestBody()` to assert method, path, headers, payload.
- Fixture builders: `$this->authBody()`, `$this->userResource()`, `$this->smsLogBody()`, `$this->deviceResource()`, `$this->error()`.
- `tests/Laravel/CacheTokenStoreTest.php` is `markTestSkipped` when Illuminate is absent (it is, in this repo — those 2 skips are expected).
- PHPUnit 11: use `#[DataProvider]` attributes, not doc-comment annotations (deprecated).

## Gotchas

- **Path-repository installs need `"azelya/sms-service": "dev-main"`** — composer resolves non-git path packages as dev-main, and `"*"` fails minimum-stability. This repo has no git repo (init only if the user asks).
- Laravel bridge classes extend Illuminate classes, so they won't `class_exists()` outside a Laravel app — that's fine and by design; guard new bridge code the same way.
- New exceptions: pass the HTTP status as 2nd constructor arg (`new FooException($message, $status, context: [...])`); subclasses are empty.
