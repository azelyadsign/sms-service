<?php

namespace Azelya\SmsService\Tests\Auth;

use Azelya\SmsService\Config;
use Azelya\SmsService\Exception\AuthenticationException;
use Azelya\SmsService\Tests\TestCase;

final class AuthServiceTest extends TestCase
{
    public function test_register_parses_auth_response_without_storing_the_token(): void
    {
        $this->queue->append($this->jsonResponse(201, $this->authBody('registration-token')));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $response = $client->auth()->register('John Doe', 'john@example.com', 'Secret123');

        $this->assertSame('registration-token', $response->accessToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertSame('john@example.com', $response->user->email);
        $this->assertSame('John Doe', $response->user->name);
        $this->assertNull($client->getToken());
    }

    public function test_register_sends_password_confirmation(): void
    {
        $this->queue->append($this->jsonResponse(201, $this->authBody()));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $client->auth()->register('John Doe', 'john@example.com', 'Secret123');

        $request = $this->lastRequest();

        $this->assertSame('/api/v1/register', $request->getUri()->getPath());

        $body = $this->requestBody($request);

        $this->assertSame('Secret123', $body['password']);
        $this->assertSame('Secret123', $body['password_confirmation']);
    }

    public function test_login_returns_and_stores_the_token(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->authBody('login-token')));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $response = $client->auth()->login('john@example.com', 'Secret123');

        $this->assertSame('login-token', $response->accessToken);
        $this->assertSame('login-token', $client->getToken());
        $this->assertSame('/api/v1/login', $this->lastRequest()->getUri()->getPath());
    }

    public function test_login_with_invalid_credentials_throws(): void
    {
        $this->queue->append($this->error(401, 'Invalid credentials.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $client->auth()->login('john@example.com', 'wrong');
    }

    public function test_logout_revokes_and_forgets_the_token(): void
    {
        $this->queue->append($this->jsonResponse(200, ['message' => 'Logged out successfully.']));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));
        $client->setToken('logout-me');

        $client->auth()->logout();

        $this->assertSame('/api/v1/logout', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('Bearer logout-me', $this->lastRequest()->getHeaderLine('Authorization'));
        $this->assertNull($client->getToken());
        $this->assertNull($this->tokenStore->load());
    }

    public function test_app_register_hits_the_app_endpoint(): void
    {
        $this->queue->append($this->jsonResponse(201, $this->authBody('app-registration-token')));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $response = $client->auth()->appRegister('My App', 'app@example.com', 'Secret123');

        $this->assertSame('app-registration-token', $response->accessToken);
        $this->assertSame('/api/v1/app/register', $this->lastRequest()->getUri()->getPath());
        $this->assertNull($client->getToken());
    }

    public function test_app_login_returns_and_stores_the_token(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->authBody('app-login-token')));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $response = $client->auth()->appLogin('app@example.com', 'Secret123');

        $this->assertSame('app-login-token', $response->accessToken);
        $this->assertSame('app-login-token', $client->getToken());
        $this->assertSame('/api/v1/app/login', $this->lastRequest()->getUri()->getPath());
    }

    public function test_app_logout_revokes_and_forgets_the_token(): void
    {
        $this->queue->append($this->jsonResponse(200, ['message' => 'Logged out successfully.']));

        $client = $this->client(new Config('https://smsgate.test/api/v1'));
        $client->setToken('app-token');

        $client->auth()->appLogout();

        $this->assertSame('/api/v1/app/logout', $this->lastRequest()->getUri()->getPath());
        $this->assertNull($client->getToken());
    }
}
