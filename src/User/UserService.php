<?php

namespace Azelya\SmsService\User;

use Azelya\SmsService\Dto\User;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Http\AuthMode;

/**
 * The authenticated user (/user).
 */
final class UserService
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * Fetch the authenticated user.
     *
     * @param  string[]  $include  Relations to include, e.g. ["roles", "device"].
     */
    public function me(array $include = []): User
    {
        $options = [];

        if ($include !== []) {
            $options['query'] = ['include' => implode(',', $include)];
        }

        $body = $this->api->request('GET', '/user', $options, AuthMode::Bearer);

        $resource = $body['data'] ?? null;

        if (! is_array($resource)) {
            throw new InvalidResponseException('The SMS gateway did not return a user resource.');
        }

        return User::fromResource(
            $resource,
            roles: $this->includedNames($body, 'roles'),
            permissions: $this->includedNames($body, 'permissions'),
        );
    }

    /**
     * Collect names from JSON:API "included" resources of the given type.
     *
     * @param  array<string, mixed>  $body
     * @return string[]
     */
    private function includedNames(array $body, string $type): array
    {
        $names = [];

        foreach ($body['included'] ?? [] as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== $type) {
                continue;
            }

            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $name = $attributes['name'] ?? $item['name'] ?? null;

            if ($name !== null) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }
}
