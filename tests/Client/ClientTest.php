<?php

namespace Azelya\SmsService\Tests\Client;

use Azelya\SmsService\Config;
use Azelya\SmsService\Exception\AuthenticationException;
use Azelya\SmsService\Exception\ConflictException;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Exception\NetworkException;
use Azelya\SmsService\Exception\NotFoundException;
use Azelya\SmsService\Exception\PermissionDeniedException;
use Azelya\SmsService\Exception\RateLimitException;
use Azelya\SmsService\Exception\SmsGatewayException;
use Azelya\SmsService\Exception\ValidationException;
use Azelya\SmsService\Tests\TestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class ClientTest extends TestCase
{
    public function test_uri_is_joined_to_base_url_with_trailing_slash_tolerance(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody()));

        $client = $this->client(new Config('https://smsgate.test/api/v1/', token: 't'));
        $client->sms()->get('sms-log-123');

        $this->assertSame('https://smsgate.test/api/v1/sms/sms-log-123', (string) $this->lastRequest()->getUri());
    }

    public function test_configured_token_is_sent_as_bearer_header(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody()));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 'pre-provisioned'));
        $client->sms()->get('sms-log-123');

        $this->assertSame('Bearer pre-provisioned', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function test_set_token_persists_to_the_store(): void
    {
        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $client->setToken('manual-token');

        $this->assertSame('manual-token', $client->getToken());
        $this->assertSame('manual-token', $this->tokenStore->load());

        $client->forgetToken();

        $this->assertNull($client->getToken());
        $this->assertNull($this->tokenStore->load());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('errorMap')]
    public function test_error_responses_map_to_typed_exceptions(int $status, string $expectedClass): void
    {
        $this->queue->append($this->error($status, 'boom'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->get('sms-log-123');
            $this->fail('Expected '.$expectedClass);
        } catch (SmsGatewayException $e) {
            $this->assertInstanceOf($expectedClass, $e);
            $this->assertSame($status, $e->statusCode());
            $this->assertSame('boom', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{int, class-string}>
     */
    public static function errorMap(): array
    {
        return [
            '401' => [401, AuthenticationException::class],
            '403' => [403, PermissionDeniedException::class],
            '404' => [404, NotFoundException::class],
            '409' => [409, ConflictException::class],
            '422' => [422, ValidationException::class],
            '429' => [429, RateLimitException::class],
            '500' => [500, SmsGatewayException::class],
        ];
    }

    public function test_validation_exception_exposes_field_errors(): void
    {
        $this->queue->append($this->error(422, 'The given data was invalid.', [
            'phone' => ['The phone field is required.'],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->get('sms-log-123');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['The phone field is required.'], $e->errorsFor('phone'));
            $this->assertSame([], $e->errorsFor('message'));
        }
    }

    public function test_rate_limit_exception_parses_retry_after_header(): void
    {
        $this->queue->append($this->error(429, 'Too Many Attempts.', headers: ['Retry-After' => '42']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->get('sms-log-123');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(42, $e->retryAfter());
        }
    }

    public function test_rate_limit_exception_falls_back_to_rate_limit_reset_header(): void
    {
        $this->queue->append($this->error(429, 'Too Many Attempts.', headers: [
            'X-RateLimit-Reset' => (string) (time() + 60),
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->get('sms-log-123');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertGreaterThanOrEqual(55, $e->retryAfter());
            $this->assertLessThanOrEqual(60, $e->retryAfter());
        }
    }

    public function test_invalid_json_body_throws_invalid_response_exception(): void
    {
        $this->queue->append(new Response(200, [], 'this is not json{{'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(InvalidResponseException::class);

        $client->sms()->get('sms-log-123');
    }

    public function test_connect_failure_wraps_into_network_exception(): void
    {
        $this->queue->append(new ConnectException(
            'cURL error 7: Connection refused',
            new Request('GET', 'https://smsgate.test/api/v1/sms/sms-log-123'),
        ));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->get('sms-log-123');
            $this->fail('Expected NetworkException');
        } catch (NetworkException $e) {
            $this->assertSame(0, $e->statusCode());
            $this->assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    public function test_bearer_request_without_credentials_throws_authentication_exception(): void
    {
        $client = $this->client(new Config('https://smsgate.test/api/v1'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No access token available');

        $client->sms()->get('sms-log-123');

        $this->assertCount(0, $this->history);
    }

    public function test_auto_login_happens_once_and_the_token_is_reused(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->authBody('auto-token')));
        $this->queue->append($this->jsonResponse(200, ['data' => $this->userResource()]));
        $this->queue->append($this->jsonResponse(200, ['data' => $this->userResource()]));

        $client = $this->client(new Config(
            'https://smsgate.test/api/v1',
            email: 'john@example.com',
            password: 'secret',
        ));

        $client->user()->me();
        $client->user()->me();

        $this->assertCount(3, $this->history);
        $this->assertSame('POST', $this->history[0]['request']->getMethod());
        $this->assertSame('/api/v1/login', $this->history[0]['request']->getUri()->getPath());
        $this->assertSame('Bearer auto-token', $this->history[1]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer auto-token', $this->history[2]['request']->getHeaderLine('Authorization'));
        $this->assertSame('auto-token', $client->getToken());
    }

    public function test_app_credentials_auto_login_uses_the_app_endpoint(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->authBody('app-token')));
        $this->queue->append($this->jsonResponse(200, ['data' => $this->userResource()]));

        $client = $this->client(new Config(
            'https://smsgate.test/api/v1',
            appEmail: 'app@example.com',
            appPassword: 'app-secret',
        ));

        $client->user()->me();

        $this->assertSame('/api/v1/app/login', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_stale_token_triggers_single_relogin_and_retry(): void
    {
        $this->queue->append($this->error(401, 'Unauthenticated.'));
        $this->queue->append($this->jsonResponse(200, $this->authBody('fresh-token')));
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody()));

        $client = $this->client(new Config(
            'https://smsgate.test/api/v1',
            email: 'john@example.com',
            password: 'secret',
        ));
        $client->setToken('stale-token');

        $log = $client->sms()->get('sms-log-123');

        $this->assertSame('sms-log-123', $log->id);
        $this->assertCount(3, $this->history);
        $this->assertSame('Bearer stale-token', $this->history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('/api/v1/login', $this->history[1]['request']->getUri()->getPath());
        $this->assertSame('Bearer fresh-token', $this->history[2]['request']->getHeaderLine('Authorization'));
        $this->assertSame('fresh-token', $client->getToken());
    }

    public function test_second_401_after_relogin_throws_authentication_exception(): void
    {
        $this->queue->append($this->error(401, 'Unauthenticated.'));
        $this->queue->append($this->jsonResponse(200, $this->authBody('fresh-token')));
        $this->queue->append($this->error(401, 'Unauthenticated.'));

        $client = $this->client(new Config(
            'https://smsgate.test/api/v1',
            email: 'john@example.com',
            password: 'secret',
        ));
        $client->setToken('stale-token');

        $this->expectException(AuthenticationException::class);

        $client->sms()->get('sms-log-123');
    }

    public function test_401_without_credentials_does_not_retry(): void
    {
        $this->queue->append($this->error(401, 'Unauthenticated.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 'stale-token'));

        $this->expectException(AuthenticationException::class);

        $client->sms()->get('sms-log-123');

        $this->assertCount(1, $this->history);
    }

    public function test_failed_login_does_not_loop(): void
    {
        $this->queue->append($this->error(401, 'Invalid credentials.'));

        $client = $this->client(new Config(
            'https://smsgate.test/api/v1',
            email: 'john@example.com',
            password: 'wrong-password',
        ));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $client->auth()->login('john@example.com', 'wrong-password');

        $this->assertCount(1, $this->history);
    }
}
