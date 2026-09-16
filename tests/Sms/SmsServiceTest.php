<?php

namespace Azelya\SmsService\Tests\Sms;

use Azelya\SmsService\Config;
use Azelya\SmsService\Enum\DeviceType;
use Azelya\SmsService\Exception\PermissionDeniedException;
use Azelya\SmsService\Exception\ValidationException;
use Azelya\SmsService\Tests\TestCase;

final class SmsServiceTest extends TestCase
{
    public function test_send_returns_delivery_ack_with_sms_log_id(): void
    {
        $this->queue->append($this->jsonResponse(202, [
            'message' => 'SMS queued for delivery.',
            'sms_log_id' => 'sms-log-123',
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $ack = $client->sms()->send('+40721234567', 'Hello');

        $this->assertSame('sms-log-123', $ack->smsLogId);
        $this->assertSame('SMS queued for delivery.', $ack->message);
        $this->assertSame('/api/v1/sms/send', $this->lastRequest()->getUri()->getPath());

        $this->assertSame(
            ['phone' => '+40721234567', 'message' => 'Hello'],
            $this->requestBody($this->lastRequest()),
        );
    }

    public function test_send_accepts_device_type_enum_and_string(): void
    {
        $this->queue->append($this->jsonResponse(202, ['message' => 'ok', 'sms_log_id' => 'sms-log-1']));
        $this->queue->append($this->jsonResponse(202, ['message' => 'ok', 'sms_log_id' => 'sms-log-2']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $client->sms()->send('+40721234567', 'Hello', DeviceType::Iot);
        $this->assertSame('iot', $this->requestBody($this->history[0]['request'])['device_type']);

        $client->sms()->send('+40721234567', 'Hello', 'android');
        $this->assertSame('android', $this->requestBody($this->history[1]['request'])['device_type']);
    }

    public function test_send_validation_error_exposes_field_errors(): void
    {
        $this->queue->append($this->error(422, 'The given data was invalid.', [
            'phone' => ['The phone field is required.'],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->send('', 'Hello');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['The phone field is required.'], $e->errorsFor('phone'));
        }
    }

    public function test_get_maps_the_flat_sms_log_body(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody([
            'status' => 'delivered',
            'device_type' => 'android',
            'external_id' => 'ext-42',
        ])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $log = $client->sms()->get('sms-log-123');

        $this->assertSame('sms-log-123', $log->id);
        $this->assertSame('+40721234567', $log->phone);
        $this->assertSame('delivered', $log->status);
        $this->assertSame('android', $log->deviceType);
        $this->assertSame('ext-42', $log->externalId);
        $this->assertSame('sent', $log->direction);
        $this->assertFalse($log->isPending());
        $this->assertTrue($log->isFinal());
        $this->assertSame('/api/v1/sms/sms-log-123', $this->lastRequest()->getUri()->getPath());
    }

    public function test_get_nullable_fields_stay_null(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody()));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $log = $client->sms()->get('sms-log-123');

        $this->assertNull($log->deviceType);
        $this->assertNull($log->externalId);
        $this->assertNull($log->rawResponse);
        $this->assertTrue($log->isPending());
        $this->assertFalse($log->isFinal());
    }

    public function test_get_of_another_users_sms_throws_permission_denied(): void
    {
        $this->queue->append($this->error(403, 'This action is unauthorized.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(PermissionDeniedException::class);

        $client->sms()->get('someone-elses-log');
    }

    public function test_retry_returns_delivery_ack(): void
    {
        $this->queue->append($this->jsonResponse(202, [
            'message' => 'SMS retry queued.',
            'sms_log_id' => 'sms-log-123',
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $ack = $client->sms()->retry('sms-log-123');

        $this->assertSame('sms-log-123', $ack->smsLogId);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/sms/sms-log-123/retry', $this->lastRequest()->getUri()->getPath());
    }

    public function test_retry_of_non_failed_sms_throws_validation_exception(): void
    {
        $this->queue->append($this->error(422, 'SMS is not in failed state.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->retry('sms-log-123');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('SMS is not in failed state.', $e->getMessage());
        }
    }
}
