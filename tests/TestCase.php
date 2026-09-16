<?php

namespace Azelya\SmsService\Tests;

use Azelya\SmsService\Config;
use Azelya\SmsService\SmsGatewayClient;
use Azelya\SmsService\TokenStore\ArrayTokenStore;
use Azelya\SmsService\TokenStore\TokenStore;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;

abstract class TestCase extends BaseTestCase
{
    protected MockHandler $queue;

    /**
     * @var array<int, array{request: RequestInterface, response: mixed, error: mixed, options: array<string, mixed>}>
     */
    protected array $history = [];

    protected ArrayTokenStore $tokenStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new MockHandler();
        $this->history = [];
        $this->tokenStore = new ArrayTokenStore();
    }

    protected function client(?Config $config = null, ?TokenStore $tokenStore = null): SmsGatewayClient
    {
        $stack = HandlerStack::create($this->queue);
        $stack->push(Middleware::history($this->history));

        $http = new Client(['handler' => $stack, 'http_errors' => false]);

        return new SmsGatewayClient(
            config: $config ?? new Config('https://smsgate.test/api/v1'),
            tokenStore: $tokenStore ?? $this->tokenStore,
            httpClient: $http,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    protected function jsonResponse(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, $headers, json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, string[]>  $errors
     * @param  array<string, string>  $headers
     */
    protected function error(int $status, string $message, array $errors = [], array $headers = []): Response
    {
        $body = ['message' => $message];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return $this->jsonResponse($status, $body, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    protected function userResource(
        string $id = '550e8400-e29b-41d4-a716-446655440000',
        string $name = 'John Doe',
        string $email = 'john@example.com',
    ): array {
        return [
            'id' => $id,
            'type' => 'users',
            'attributes' => [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'created_at' => '2026-08-12T10:00:00.000000Z',
            ],
        ];
    }

    /**
     * The {user, access_token, token_type} body shared by login/register.
     *
     * @return array<string, mixed>
     */
    protected function authBody(string $token = 'access-token-123'): array
    {
        return [
            'user' => ['data' => $this->userResource()],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * The flat SMS log body of GET /sms/{id}.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function smsLogBody(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sms-log-123',
            'phone' => '+40721234567',
            'message' => 'Hello',
            'direction' => 'sent',
            'device_type' => null,
            'status' => 'pending',
            'external_id' => null,
            'raw_response' => null,
            'created_at' => '2026-08-12T10:00:00.000000Z',
            'updated_at' => '2026-08-12T10:00:01.000000Z',
        ], $overrides);
    }

    /**
     * The JSON:API device resource {data: {id, type, attributes}}.
     *
     * @param  array<string, mixed>  $attributeOverrides
     * @return array<string, mixed>
     */
    protected function deviceResource(array $attributeOverrides = []): array
    {
        $attributes = array_merge([
            'id' => 'device-1',
            'name' => 'Galaxy S22',
            'type' => 'android',
            'token' => 'static-device-token-123',
            'is_active' => true,
            'created_at' => '2026-08-12T10:00:00.000000Z',
        ], $attributeOverrides);

        return [
            'data' => [
                'id' => $attributes['id'],
                'type' => 'device-tokens',
                'attributes' => $attributes,
            ],
        ];
    }

    protected function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestBody(RequestInterface $request): array
    {
        return json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
