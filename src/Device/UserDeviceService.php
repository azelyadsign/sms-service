<?php

namespace Azelya\SmsService\Device;

use Azelya\SmsService\Dto\Device;
use Azelya\SmsService\Enum\DeviceType;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;

/**
 * The authenticated user's registered device (/user/device). One device per
 * user; the gateway generates the device token.
 */
final class UserDeviceService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * Fetch the registered device. Throws NotFoundException when none exists.
     */
    public function get(): Device
    {
        $body = $this->api->request('GET', '/user/device', [], AuthMode::Bearer);

        return $this->extractDevice($body);
    }

    /**
     * Register or update the device. The gateway generates a fresh token on
     * every call — the returned Device carries it.
     */
    public function create(string $name, DeviceType|string $type): Device
    {
        $body = $this->api->request('POST', '/user/device', [
            'json' => [
                'name' => $name,
                'type' => $type instanceof DeviceType ? $type->value : $type,
            ],
        ], AuthMode::Bearer);

        return $this->extractDevice($body);
    }

    /**
     * Remove the registered device.
     */
    public function delete(): void
    {
        $this->api->request('DELETE', '/user/device', [], AuthMode::Bearer);
    }

    /**
     * The gateway wraps the device resource of the create endpoint in a
     * top-level array ([{data: {...}}]) while the show endpoint does not.
     * Unwrap defensively so both shapes survive.
     *
     * @param  array<string, mixed>  $body
     */
    private function extractDevice(array $body): Device
    {
        if (isset($body[0]) && is_array($body[0])) {
            $body = $body[0];
        }

        $resource = $body['data'] ?? null;

        if (! is_array($resource)) {
            throw new InvalidResponseException('The SMS gateway did not return a device resource.');
        }

        return Device::fromResource($resource);
    }
}
