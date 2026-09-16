<?php

namespace Azelya\SmsService\Sms;

use Azelya\SmsService\Dto\DeliveryAck;
use Azelya\SmsService\Dto\PaginatedSms;
use Azelya\SmsService\Dto\SmsConversation;
use Azelya\SmsService\Dto\SmsLog;
use Azelya\SmsService\Enum\DeviceType;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;
use Azelya\SmsService\Support\SmsStatusPoller;

/**
 * SMS endpoints: /sms, /sms/send, /sms/{id}, /sms/{id}/conversation, /sms/{id}/retry.
 */
final class SmsService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * Queue an SMS for delivery. Returns immediately (202); the message is
     * delivered asynchronously — poll with get()/waitForStatus().
     *
     * Throws ValidationException (errors under "device_type") when the user
     * has no active device that can deliver the message.
     */
    public function send(string $phone, string $message, DeviceType|string|null $deviceType = null): DeliveryAck
    {
        $payload = [
            'phone' => $phone,
            'message' => $message,
        ];

        if ($deviceType !== null) {
            $payload['device_type'] = $deviceType instanceof DeviceType ? $deviceType->value : $deviceType;
        }

        $body = $this->api->request('POST', '/sms/send', ['json' => $payload], AuthMode::Bearer);

        return DeliveryAck::fromArray($body);
    }

    /**
     * List the authenticated user's messages, newest first, each with the
     * device that handled it.
     *
     * @param  array<string, mixed>  $query  Supported: page, per_page.
     */
    public function list(array $query = []): PaginatedSms
    {
        $body = $this->api->request(
            'GET',
            '/sms',
            $query === [] ? [] : ['query' => $query],
            AuthMode::Bearer,
        );

        return PaginatedSms::fromArray($body);
    }

    /**
     * Fetch a sent message together with the replies it received (matched
     * by external_id, oldest first).
     */
    public function conversation(string $smsLogId): SmsConversation
    {
        $body = $this->api->request('GET', '/sms/'.$smsLogId.'/conversation', [], AuthMode::Bearer);

        return SmsConversation::fromArray($body);
    }

    /**
     * Fetch a message's current delivery status.
     */
    public function get(string $smsLogId): SmsLog
    {
        $body = $this->api->request('GET', '/sms/'.$smsLogId, [], AuthMode::Bearer);

        return SmsLog::fromArray($body);
    }

    /**
     * Re-queue a failed message. Throws ValidationException when the message
     * is not in the failed state.
     */
    public function retry(string $smsLogId): DeliveryAck
    {
        $body = $this->api->request('POST', '/sms/'.$smsLogId.'/retry', [], AuthMode::Bearer);

        return DeliveryAck::fromArray($body);
    }

    /**
     * Send a message and poll until its delivery reaches a final state
     * (sent, delivered or failed).
     */
    public function sendAndWait(
        string $phone,
        string $message,
        DeviceType|string|null $deviceType = null,
        int $timeoutSeconds = 120,
        float $pollIntervalSeconds = 2.0,
        ?callable $onStatusChange = null,
    ): SmsLog {
        $ack = $this->send($phone, $message, $deviceType);

        return $this->waitForStatus($ack->smsLogId, $timeoutSeconds, $pollIntervalSeconds, $onStatusChange);
    }

    /**
     * Poll a queued message until its status leaves "pending".
     *
     * @param  callable(SmsLog): void|null  $onStatusChange  Invoked whenever the observed status changes.
     */
    public function waitForStatus(
        string $smsLogId,
        int $timeoutSeconds = 120,
        float $pollIntervalSeconds = 2.0,
        ?callable $onStatusChange = null,
    ): SmsLog {
        return (new SmsStatusPoller($this))->wait(
            $smsLogId,
            $timeoutSeconds,
            $pollIntervalSeconds,
            $onStatusChange,
        );
    }
}
