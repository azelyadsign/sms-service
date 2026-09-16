<?php

namespace Azelya\SmsService\Admin;

use Azelya\SmsService\Dto\Device;
use Azelya\SmsService\Dto\PaginatedDevices;
use Azelya\SmsService\Dto\PaginatedUsers;
use Azelya\SmsService\Dto\User;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;

/**
 * Admin endpoints (/admin/users, /admin/devices), usable by Admin-role tokens only.
 */
final class AdminService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * List all users (paginated).
     *
     * @param  array<string, mixed>  $query  Supported: sort (e.g. "name,-created_at"), per_page.
     */
    public function listUsers(array $query = []): PaginatedUsers
    {
        $body = $this->api->request(
            'GET',
            '/admin/users',
            $query === [] ? [] : ['query' => $query],
            AuthMode::Bearer,
        );

        return PaginatedUsers::fromArray($body);
    }

    /**
     * Approve a user by assigning the Client role (enables SMS sending).
     * Throws ConflictException when the user is already approved.
     */
    public function approve(string $userId): User
    {
        return $this->updateUser('PATCH', $userId, 'approve');
    }

    /**
     * Revoke all roles from a user. Throws ConflictException when the user
     * has no roles to revoke.
     */
    public function revoke(string $userId): User
    {
        return $this->updateUser('PATCH', $userId, 'revoke');
    }

    /**
     * List all devices, including unlinked gateway devices (paginated).
     *
     * @param  array<string, mixed>  $query  Supported: sort (name, type, created_at, is_active — "-" prefix for desc), per_page, page.
     */
    public function listDevices(array $query = []): PaginatedDevices
    {
        $body = $this->api->request(
            'GET',
            '/admin/devices',
            $query === [] ? [] : ['query' => $query],
            AuthMode::Bearer,
        );

        return PaginatedDevices::fromArray($body);
    }

    /**
     * Activate or deactivate a device. Deactivated devices never receive SMS requests.
     */
    public function setDeviceActive(string $deviceId, bool $isActive): Device
    {
        $body = $this->api->request('PATCH', '/admin/devices/'.$deviceId, [
            'json' => ['is_active' => $isActive],
        ], AuthMode::Bearer);

        return $this->extractDevice($body);
    }

    private function updateUser(string $method, string $userId, string $action): User
    {
        $body = $this->api->request($method, '/admin/users/'.$userId.'/'.$action, [], AuthMode::Bearer);

        $resource = $body['user']['data'] ?? null;

        if (! is_array($resource)) {
            throw new InvalidResponseException('The SMS gateway did not return the updated user.');
        }

        return User::fromResource($resource);
    }

    /**
     * Unwrap the JSON:API device resource ({data: {...}}) returned by the toggle endpoint.
     *
     * @param  array<string, mixed>  $body
     */
    private function extractDevice(array $body): Device
    {
        $resource = $body['data'] ?? null;

        if (! is_array($resource) || isset($resource[0])) {
            throw new InvalidResponseException('The SMS gateway did not return the updated device.');
        }

        return Device::fromResource($resource);
    }
}
