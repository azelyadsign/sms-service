<?php

namespace Azelya\SmsService\Device;

use Azelya\SmsService\Dto\Device;
use Azelya\SmsService\Enum\DeviceType;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;

/**
 * The authenticated user's registered devices (/user/devices). A user may
 * register multiple devices; the gateway generates a token for each one.
 */
final class UserDeviceService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * List all devices registered to the authenticated user. The gateway
     * returns a JSON:API collection that is NOT paginated.
     *
     * @return Device[]
     */
    public function list(): array
    {
        $body = $this->api->request('GET', '/user/devices', [], AuthMode::Bearer);

        $resources = $body['data'] ?? null;

        if (! is_array($resources)) {
            throw new InvalidResponseException('The SMS gateway did not return a device collection.');
        }

        $devices = [];

        foreach ($resources as $resource) {
            if (is_array($resource)) {
                $devices[] = Device::fromResource($resource);
            }
        }

        return $devices;
    }

    /**
     * Register an additional device for the authenticated user. Always
     * creates a new device (never updates); the gateway generates the token.
     *
     * @param  DeviceType|string  $type  Any alpha_dash string of at least 3
     *                                   characters ('android', 'ios', 'galaxy-s22', ...).
     */
    public function create(string $name, DeviceType|string $type): Device
    {
        $body = $this->api->request('POST', '/user/devices', [
            'json' => [
                'name' => $name,
                'type' => $type instanceof DeviceType ? $type->value : $type,
            ],
        ], AuthMode::Bearer);

        return $this->extractDevice($body);
    }

    /**
     * Fetch a specific device of the authenticated user. Throws
     * NotFoundException when the id is unknown and PermissionDeniedException
     * for another user's device.
     */
    public function get(string $deviceId): Device
    {
        $body = $this->api->request('GET', '/user/devices/'.$deviceId, [], AuthMode::Bearer);

        return $this->extractDevice($body);
    }

    /**
     * Remove a specific device of the authenticated user.
     */
    public function delete(string $deviceId): void
    {
        $this->api->request('DELETE', '/user/devices/'.$deviceId, [], AuthMode::Bearer);
    }

    /**
     * Unwrap the JSON:API device resource ({data: {...}}) returned by the
     * show/create endpoints.
     *
     * @param  array<string, mixed>  $body
     */
    private function extractDevice(array $body): Device
    {
        $resource = $body['data'] ?? null;

        if (! is_array($resource) || isset($resource[0])) {
            throw new InvalidResponseException('The SMS gateway did not return a device resource.');
        }

        return Device::fromResource($resource);
    }
}
