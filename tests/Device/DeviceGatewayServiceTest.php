<?php

namespace Azelya\SmsService\Tests\Device;

use Azelya\SmsService\Config;
use Azelya\SmsService\Exception\AuthenticationException;
use Azelya\SmsService\Tests\TestCase;

final class DeviceGatewayServiceTest extends TestCase
{
    public function test_authorize_channel_returns_device_auth(): void
    {
        $this->queue->append($this->jsonResponse(200, [
            'auth' => 'reverb-key:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', deviceToken: 'device-secret'));

        $auth = $client->deviceGateway()->authorizeChannel(
            '123.456',
            'private-sms.android.550e8400-e29b-41d4-a716-446655440000',
        );

        $this->assertSame('reverb-key', $auth->key());
        $this->assertSame('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $auth->signature());
        $this->assertSame('/api/v1/device/broadcasting/auth', $this->lastRequest()->getUri()->getPath());

        $this->assertSame(
            [
                'socket_id' => '123.456',
                'channel_name' => 'private-sms.android.550e8400-e29b-41d4-a716-446655440000',
            ],
            $this->requestBody($this->lastRequest()),
        );
    }

    public function test_device_requests_send_x_device_token_header_instead_of_bearer(): void
    {
        $this->queue->append($this->jsonResponse(200, ['auth' => 'k:s']));
        $this->queue->append($this->jsonResponse(201, ['message' => 'Reply recorded.', 'sms_log_id' => 'sms-log-9']));
        $this->queue->append($this->jsonResponse(200, ['message' => 'Status updated.', 'sms_log_id' => 'sms-log-9']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', deviceToken: 'device-secret'));

        $client->deviceGateway()->authorizeChannel('1.2', 'private-sms.android.x');
        $client->deviceGateway()->reply('sms.reply', '+40721234567', 'Thanks!');
        $client->deviceGateway()->updateStatus('sms-log-9', 'delivered');

        foreach ($this->history as $entry) {
            $this->assertSame('device-secret', $entry['request']->getHeaderLine('X-Device-Token'));
            $this->assertSame('', $entry['request']->getHeaderLine('Authorization'));
        }
    }

    public function test_reply_sends_the_nested_data_payload(): void
    {
        $this->queue->append($this->jsonResponse(201, ['message' => 'Reply recorded.', 'sms_log_id' => 'sms-log-9']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', deviceToken: 'device-secret'));

        $ack = $client->deviceGateway()->reply('sms.reply', '+40721234567', 'Thanks!', externalId: 'ext-1');

        $this->assertSame('sms-log-9', $ack->smsLogId);
        $this->assertSame('/api/v1/device/reply', $this->lastRequest()->getUri()->getPath());

        $body = $this->requestBody($this->lastRequest());

        $this->assertSame('sms.reply', $body['event']);
        $this->assertSame('+40721234567', $body['data']['phone']);
        $this->assertSame('Thanks!', $body['data']['message']);
        $this->assertSame('ext-1', $body['data']['external_id']);
    }

    public function test_reply_omits_null_fields(): void
    {
        $this->queue->append($this->jsonResponse(201, ['message' => 'Reply recorded.', 'sms_log_id' => 'sms-log-9']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', deviceToken: 'device-secret'));

        $client->deviceGateway()->reply('sms.reply', '+40721234567');

        $body = $this->requestBody($this->lastRequest());

        $this->assertArrayNotHasKey('message', $body['data']);
        $this->assertArrayNotHasKey('status', $body);
        $this->assertArrayNotHasKey('raw_response', $body);
    }

    public function test_update_status_sends_the_payload(): void
    {
        $this->queue->append($this->jsonResponse(200, ['message' => 'Status updated.', 'sms_log_id' => 'sms-log-9']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', deviceToken: 'device-secret'));

        $ack = $client->deviceGateway()->updateStatus('sms-log-9', 'delivered', externalId: 'ext-1');

        $this->assertSame('sms-log-9', $ack->smsLogId);
        $this->assertSame('/api/v1/device/status', $this->lastRequest()->getUri()->getPath());

        $body = $this->requestBody($this->lastRequest());

        $this->assertSame('sms-log-9', $body['sms_log_id']);
        $this->assertSame('delivered', $body['status']);
        $this->assertSame('ext-1', $body['external_id']);
        $this->assertArrayNotHasKey('raw_response', $body);
    }

    public function test_invalid_device_token_throws_authentication_exception_without_retry(): void
    {
        $this->queue->append($this->error(401, 'Invalid or inactive device token.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', deviceToken: 'bad-token'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid or inactive device token.');

        $client->deviceGateway()->updateStatus('sms-log-9', 'delivered');

        $this->assertCount(1, $this->history);
    }

    public function test_device_endpoint_without_device_token_config_throws(): void
    {
        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 'bearer-token'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No device token configured');

        $client->deviceGateway()->updateStatus('sms-log-9', 'delivered');

        $this->assertCount(0, $this->history);
    }
}
