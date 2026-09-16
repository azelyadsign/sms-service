<?php

namespace Azelya\SmsService\Admin;

use Azelya\SmsService\Dto\PaginatedUsers;
use Azelya\SmsService\Dto\User;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;

/**
 * Admin endpoints (/admin/users), usable by Admin-role tokens only.
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

    private function updateUser(string $method, string $userId, string $action): User
    {
        $body = $this->api->request($method, '/admin/users/'.$userId.'/'.$action, [], AuthMode::Bearer);

        $resource = $body['user']['data'] ?? null;

        if (! is_array($resource)) {
            throw new InvalidResponseException('The SMS gateway did not return the updated user.');
        }

        return User::fromResource($resource);
    }
}
