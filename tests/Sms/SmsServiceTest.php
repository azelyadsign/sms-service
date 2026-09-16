<?php

namespace Azelya\SmsService\Tests\Sms;

use Azelya\SmsService\Config;
use Azelya\SmsService\Dto\SmsDevice;
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
        $this->assertNull($log->device);
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
        $this->assertNull($log->device);
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

    public function test_send_without_an_active_device_throws_validation(): void
    {
        $this->queue->append($this->error(422, 'The given data was invalid.', [
            'device_type' => ['No active device is available to send SMS.'],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->send('+40721234567', 'Hello');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['No active device is available to send SMS.'], $e->errorsFor('device_type'));
        }
    }

    public function test_list_parses_the_laravel_paginator_body(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->paginatedSmsBody([
            ['device' => $this->smsDeviceData()],
            ['id' => 'sms-log-2', 'device' => null],
        ], [
            'current_page' => 2,
            'last_page' => 3,
            'total' => 42,
            'next_page_url' => 'https://smsgate.test/api/v1/sms?page=3',
            'prev_page_url' => 'https://smsgate.test/api/v1/sms?page=1',
        ])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $page = $client->sms()->list();

        $this->assertCount(2, $page->sms);
        $this->assertInstanceOf(SmsDevice::class, $page->sms[0]->device);
        $this->assertSame('device-1', $page->sms[0]->device?->id);
        $this->assertSame('Galaxy S22', $page->sms[0]->device?->name);
        $this->assertSame('android', $page->sms[0]->device?->type);
        $this->assertNull($page->sms[1]->device);
        $this->assertSame(42, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(3, $page->lastPage());
        $this->assertSame(15, $page->perPage());
        $this->assertSame('https://smsgate.test/api/v1/sms?page=3', $page->nextPage());
        $this->assertSame('https://smsgate.test/api/v1/sms?page=1', $page->prevPage());
        $this->assertSame('/api/v1/sms', $this->lastRequest()->getUri()->getPath());
    }

    public function test_list_of_the_last_page_has_no_next_page(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->paginatedSmsBody([[]], [
            'current_page' => 3,
            'last_page' => 3,
            'prev_page_url' => 'https://smsgate.test/api/v1/sms?page=2',
        ])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $page = $client->sms()->list();

        $this->assertNull($page->nextPage());
        $this->assertSame('https://smsgate.test/api/v1/sms?page=2', $page->prevPage());
    }

    public function test_list_sends_page_and_per_page_query(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->paginatedSmsBody()));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $client->sms()->list(['page' => 3, 'per_page' => 50]);

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);

        $this->assertSame('3', $query['page']);
        $this->assertSame('50', $query['per_page']);
    }

    public function test_list_of_an_empty_page_returns_no_messages(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->paginatedSmsBody()));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $page = $client->sms()->list();

        $this->assertCount(0, $page->sms);
        $this->assertSame(0, $page->total());
    }

    public function test_conversation_returns_the_message_and_its_replies(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsConversationBody(
            ['id' => 'sms-log-123', 'status' => 'delivered'],
            [['id' => 'reply-1', 'direction' => 'reply', 'device' => $this->smsDeviceData(['name' => 'iPhone 15', 'type' => 'ios'])]],
        )));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $conversation = $client->sms()->conversation('sms-log-123');

        $this->assertSame('sms-log-123', $conversation->sms->id);
        $this->assertSame('delivered', $conversation->sms->status);
        $this->assertCount(1, $conversation->replies);
        $this->assertSame('reply-1', $conversation->replies[0]->id);
        $this->assertSame('reply', $conversation->replies[0]->direction);
        $this->assertSame('iPhone 15', $conversation->replies[0]->device?->name);
        $this->assertSame('ios', $conversation->replies[0]->device?->type);
        $this->assertSame('/api/v1/sms/sms-log-123/conversation', $this->lastRequest()->getUri()->getPath());
    }

    public function test_conversation_without_replies_returns_an_empty_list(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsConversationBody(['id' => 'sms-log-123'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $conversation = $client->sms()->conversation('sms-log-123');

        $this->assertSame([], $conversation->replies);
    }

    public function test_conversation_of_another_users_sms_throws_permission_denied(): void
    {
        $this->queue->append($this->error(403, 'This action is unauthorized.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(PermissionDeniedException::class);

        $client->sms()->conversation('someone-elses-log');
    }
}
