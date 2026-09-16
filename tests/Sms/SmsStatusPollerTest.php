<?php

namespace Azelya\SmsService\Tests\Sms;

use Azelya\SmsService\Config;
use Azelya\SmsService\Dto\SmsLog;
use Azelya\SmsService\Exception\SmsTimeoutException;
use Azelya\SmsService\Tests\TestCase;

final class SmsStatusPollerTest extends TestCase
{
    public function test_returns_when_the_status_leaves_pending_and_reports_transitions(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'pending'])));
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'pending'])));
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'delivered'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $observed = [];

        $log = $client->sms()->waitForStatus(
            'sms-log-123',
            10,
            0.01,
            function (SmsLog $log) use (&$observed): void {
                $observed[] = $log->status;
            },
        );

        $this->assertSame('delivered', $log->status);
        $this->assertSame(['delivered'], $observed);
        $this->assertCount(3, $this->history);
    }

    public function test_sent_stops_the_wait(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'pending'])));
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'sent'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $log = $client->sms()->waitForStatus('sms-log-123', 10, 0.01);

        $this->assertSame('sent', $log->status);
        $this->assertTrue($log->isFinal());
        $this->assertCount(2, $this->history);
    }

    public function test_failed_is_a_final_state(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'pending'])));
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'failed'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $log = $client->sms()->waitForStatus('sms-log-123', 10, 0.01);

        $this->assertSame('failed', $log->status);
        $this->assertTrue($log->isFinal());
    }

    public function test_already_final_status_returns_immediately_without_callback(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'sent'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $calls = 0;

        $log = $client->sms()->waitForStatus('sms-log-123', 10, 0.01, function () use (&$calls): void {
            $calls++;
        });

        $this->assertSame('sent', $log->status);
        $this->assertSame(0, $calls);
        $this->assertCount(1, $this->history);
    }

    public function test_timeout_throws_sms_timeout_exception_with_last_status(): void
    {
        for ($i = 0; $i < 150; $i++) {
            $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'pending'])));
        }

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->sms()->waitForStatus('sms-log-123', 1, 0.01);
            $this->fail('Expected SmsTimeoutException');
        } catch (SmsTimeoutException $e) {
            $this->assertSame('pending', $e->lastStatus());
            $this->assertStringContainsString('sms-log-123', $e->getMessage());
        }
    }

    public function test_send_and_wait_composes_send_and_poll(): void
    {
        $this->queue->append($this->jsonResponse(202, [
            'message' => 'SMS queued for delivery.',
            'sms_log_id' => 'sms-log-123',
        ]));
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'pending'])));
        $this->queue->append($this->jsonResponse(200, $this->smsLogBody(['status' => 'sent'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $log = $client->sms()->sendAndWait('+40721234567', 'Hello', pollIntervalSeconds: 0.01);

        $this->assertSame('sent', $log->status);
        $this->assertSame('/api/v1/sms/send', $this->history[0]['request']->getUri()->getPath());
        $this->assertSame('/api/v1/sms/sms-log-123', $this->history[1]['request']->getUri()->getPath());
    }
}
