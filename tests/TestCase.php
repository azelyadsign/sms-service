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
     * Default device attributes of the gateway's DeviceTokenResource.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function deviceAttributes(array $overrides = []): array
    {
        return array_merge([
            'id' => 'device-1',
            'name' => 'Galaxy S22',
            'type' => 'android',
            'token' => 'static-device-token-123',
            'is_active' => true,
            'created_at' => '2026-08-12T10:00:00.000000Z',
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
        $attributes = $this->deviceAttributes($attributeOverrides);

        return [
            'data' => [
                'id' => $attributes['id'],
                'type' => 'device-tokens',
                'attributes' => $attributes,
            ],
        ];
    }

    /**
     * A JSON:API device collection body ({data: [...]}) as returned by
     * GET /user/devices. One attribute set per device; an empty list yields
     * an empty collection.
     *
     * @param  array<int, array<string, mixed>>  $attributeOverridesList
     * @return array<string, mixed>
     */
    protected function deviceCollection(array $attributeOverridesList = []): array
    {
        $resources = [];

        foreach ($attributeOverridesList as $overrides) {
            $attributes = $this->deviceAttributes($overrides);
            $resources[] = [
                'id' => $attributes['id'],
                'type' => 'device-tokens',
                'attributes' => $attributes,
            ];
        }

        return ['data' => $resources];
    }

    /**
     * A paginated JSON:API device collection body as returned by
     * GET /admin/devices.
     *
     * @param  array<int, array<string, mixed>>  $attributeOverridesList
     * @param  array<string, mixed>  $overrides  Keys merged at the top level (e.g. a links override).
     * @return array<string, mixed>
     */
    protected function paginatedDevicesBody(array $attributeOverridesList = [], array $overrides = []): array
    {
        $collection = $this->deviceCollection($attributeOverridesList);

        return array_merge([
            'data' => $collection['data'],
            'links' => [
                'first' => 'https://smsgate.test/api/v1/admin/devices?page=1',
                'last' => 'https://smsgate.test/api/v1/admin/devices?page=1',
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => count($collection['data']),
            ],
        ], $overrides);
    }

    /**
     * The nested device data ({id, name, type}) attached to SMS logs by the
     * list and conversation endpoints.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function smsDeviceData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'device-1',
            'name' => 'Galaxy S22',
            'type' => 'android',
        ], $overrides);
    }

    /**
     * A plain Laravel paginator body as returned by GET /sms.
     *
     * @param  array<int, array<string, mixed>>  $logOverridesList  One smsLogBody() override set per entry.
     * @param  array<string, mixed>  $overrides  Keys merged at the top level (e.g. total, next_page_url).
     * @return array<string, mixed>
     */
    protected function paginatedSmsBody(array $logOverridesList = [], array $overrides = []): array
    {
        $data = array_map(
            fn (array $logOverrides): array => $this->smsLogBody($logOverrides),
            $logOverridesList,
        );

        return array_merge([
            'current_page' => 1,
            'data' => $data,
            'first_page_url' => 'https://smsgate.test/api/v1/sms?page=1',
            'from' => 1,
            'last_page' => 1,
            'last_page_url' => 'https://smsgate.test/api/v1/sms?page=1',
            'links' => [],
            'next_page_url' => null,
            'path' => 'https://smsgate.test/api/v1/sms',
            'per_page' => 15,
            'prev_page_url' => null,
            'to' => count($data),
            'total' => count($data),
        ], $overrides);
    }

    /**
     * The {sms, replies} body returned by GET /sms/{id}/conversation.
     *
     * @param  array<string, mixed>  $smsOverrides
     * @param  array<int, array<string, mixed>>  $replyOverridesList
     * @return array<string, mixed>
     */
    protected function smsConversationBody(array $smsOverrides = [], array $replyOverridesList = []): array
    {
        return [
            'sms' => $this->smsLogBody($smsOverrides),
            'replies' => array_map(
                fn (array $replyOverrides): array => $this->smsLogBody($replyOverrides),
                $replyOverridesList,
            ),
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
