<?php

namespace Azelya\SmsService\Auth;

use Azelya\SmsService\Dto\AuthResponse;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;

/**
 * Authentication endpoints: /register, /login, /logout and their /app/* variants.
 */
final class AuthService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * Register a new user. New users have no role until an admin approves
     * them, so SMS endpoints return 403 until then.
     */
    public function register(string $name, string $email, string $password): AuthResponse
    {
        return $this->registerAt('/register', $name, $email, $password);
    }

    /**
     * Register an app client. AppClients can send SMS immediately.
     */
    public function appRegister(string $name, string $email, string $password): AuthResponse
    {
        return $this->registerAt('/app/register', $name, $email, $password);
    }

    /**
     * Log in and persist the issued access token.
     */
    public function login(string $email, string $password): AuthResponse
    {
        return $this->api->authenticate($email, $password);
    }

    /**
     * Log in through the app flow and persist the issued access token.
     */
    public function appLogin(string $email, string $password): AuthResponse
    {
        return $this->api->authenticate($email, $password, app: true);
    }

    /**
     * Revoke the current access token and drop it from the token store.
     */
    public function logout(): void
    {
        $this->api->request('POST', '/logout', [], AuthMode::Bearer);
        $this->api->forgetToken();
    }

    /**
     * Revoke the current app access token and drop it from the token store.
     */
    public function appLogout(): void
    {
        $this->api->request('POST', '/app/logout', [], AuthMode::Bearer);
        $this->api->forgetToken();
    }

    private function registerAt(string $uri, string $name, string $email, string $password): AuthResponse
    {
        $body = $this->api->request('POST', $uri, [
            'json' => [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
            ],
        ], AuthMode::None);

        return AuthResponse::fromArray($body);
    }
}
