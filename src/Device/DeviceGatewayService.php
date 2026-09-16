<?php

namespace Azelya\SmsService\Device;

use Azelya\SmsService\Dto\DeliveryAck;
use Azelya\SmsService\Dto\DeviceAuth;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;

/**
 * Gateway device endpoints (/device/*), authenticated with the static
 * X-Device-Token header instead of a bearer token. These are the endpoints
 * the Android/IoT gateway devices themselves call.
 */
final class DeviceGatewayService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * Authorize a Reverb/Pusher private channel subscription for a device.
     * The returned DeviceAuth string is passed to the websocket client.
     */
    public function authorizeChannel(string $socketId, string $channelName): DeviceAuth
    {
        $body = $this->api->request('POST', '/device/broadcasting/auth', [
            'json' => [
                'socket_id' => $socketId,
                'channel_name' => $channelName,
            ],
        ], AuthMode::Device);

        return DeviceAuth::fromArray($body);
    }

    /**
     * Record an incoming SMS reply from a device.
     */
    public function reply(
        string $event,
        string $phone,
        ?string $message = null,
        ?string $externalId = null,
        ?string $status = null,
        mixed $rawResponse = null,
        ?string $repliedAt = null,
    ): DeliveryAck {
        $body = $this->api->request('POST', '/device/reply', [
            'json' => array_filter([
                'event' => $event,
                'data' => array_filter([
                    'phone' => $phone,
                    'message' => $message,
                    'external_id' => $externalId,
                ], static fn (mixed $value): bool => $value !== null),
                'status' => $status,
                'external_id' => $externalId,
                'raw_response' => $rawResponse,
                'replied_at' => $repliedAt,
            ], static fn (mixed $value): bool => $value !== null),
        ], AuthMode::Device);

        return DeliveryAck::fromArray($body);
    }

    /**
     * Report the delivery status of a queued SMS back to the gateway.
     */
    public function updateStatus(
        string $smsLogId,
        string $status,
        mixed $rawResponse = null,
        ?string $externalId = null,
    ): DeliveryAck {
        $body = $this->api->request('POST', '/device/status', [
            'json' => array_filter([
                'sms_log_id' => $smsLogId,
                'status' => $status,
                'raw_response' => $rawResponse,
                'external_id' => $externalId,
            ], static fn (mixed $value): bool => $value !== null),
        ], AuthMode::Device);

        return DeliveryAck::fromArray($body);
    }
}
