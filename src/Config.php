<?php

namespace Azelya\SmsService;

use InvalidArgumentException;

/**
 * Immutable client configuration.
 */
final class Config
{
    /**
     * @param  string  $baseUrl  Root of the gateway API, e.g. "https://smsgate.test/api/v1".
     * @param  string|null  $email  Email for the user auth flow; enables auto-login.
     * @param  string|null  $password  Password for the user auth flow.
     * @param  string|null  $appEmail  Email for the app auth flow (AppClient); takes precedence over $email for auto-login.
     * @param  string|null  $appPassword  Password for the app auth flow.
     * @param  string|null  $token  Pre-provisioned access token; skips auto-login entirely.
     * @param  string|null  $deviceToken  Static device token for the gateway device endpoints (X-Device-Token).
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?string $appEmail = null,
        public readonly ?string $appPassword = null,
        public readonly ?string $token = null,
        public readonly ?string $deviceToken = null,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly bool $verify = true,
        public readonly bool $retryOnUnauthorized = true,
    ) {
        if (trim($baseUrl) === '') {
            throw new InvalidArgumentException('The base URL cannot be empty.');
        }
    }

    /**
     * Build a config from the snake_case keys used by the Laravel bridge config.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            baseUrl: (string) ($config['base_url'] ?? throw new InvalidArgumentException('The "base_url" option is required.')),
            email: isset($config['email']) ? (string) $config['email'] : null,
            password: isset($config['password']) ? (string) $config['password'] : null,
            appEmail: isset($config['app_email']) ? (string) $config['app_email'] : null,
            appPassword: isset($config['app_password']) ? (string) $config['app_password'] : null,
            token: isset($config['token']) ? (string) $config['token'] : null,
            deviceToken: isset($config['device_token']) ? (string) $config['device_token'] : null,
            timeout: (float) ($config['timeout'] ?? 30.0),
            connectTimeout: (float) ($config['connect_timeout'] ?? 10.0),
            verify: (bool) ($config['verify'] ?? true),
            retryOnUnauthorized: (bool) ($config['retry_on_unauthorized'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'email' => $this->email,
            'password' => $this->password,
            'app_email' => $this->appEmail,
            'app_password' => $this->appPassword,
            'token' => $this->token,
            'device_token' => $this->deviceToken,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'verify' => $this->verify,
            'retry_on_unauthorized' => $this->retryOnUnauthorized,
        ];
    }

    /**
     * The credentials to use for auto-login. App credentials win when both
     * flows are configured. Null when no credentials are available.
     *
     * @return array{email: string, password: string, app: bool}|null
     */
    public function credentials(): ?array
    {
        if ($this->appEmail !== null && $this->appPassword !== null) {
            return ['email' => $this->appEmail, 'password' => $this->appPassword, 'app' => true];
        }

        if ($this->email !== null && $this->password !== null) {
            return ['email' => $this->email, 'password' => $this->password, 'app' => false];
        }

        return null;
    }
}
