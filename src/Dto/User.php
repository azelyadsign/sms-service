<?php

namespace Azelya\SmsService\Dto;

use Azelya\SmsService\Exception\InvalidResponseException;

final class User extends Dto
{
    /**
     * @param  string[]  $roles  Role names, populated when the response includes them.
     * @param  string[]  $permissions  Permission names, populated when the response includes them.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $createdAt = null,
        public readonly array $roles = [],
        public readonly array $permissions = [],
    ) {
    }

    public static function fromArray(array $data): static
    {
        return self::fromResource($data);
    }

    /**
     * Build the user from a JSON:API resource object:
     * {id, type, attributes: {id, name, email, created_at}}.
     *
     * @param  array<string, mixed>  $resource
     * @param  string[]  $roles
     * @param  string[]  $permissions
     */
    public static function fromResource(array $resource, array $roles = [], array $permissions = []): static
    {
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];

        $id = (string) ($resource['id'] ?? $attributes['id'] ?? '');
        $name = (string) ($attributes['name'] ?? '');
        $email = (string) ($attributes['email'] ?? '');

        if ($id === '' || $name === '' || $email === '') {
            throw new InvalidResponseException('The SMS gateway returned a user resource without id, name or email.');
        }

        $roles = array_merge($roles, is_array($resource['roles'] ?? null) ? $resource['roles'] : []);
        $permissions = array_merge($permissions, is_array($resource['permissions'] ?? null) ? $resource['permissions'] : []);

        return new self(
            id: $id,
            name: $name,
            email: $email,
            createdAt: isset($attributes['created_at']) ? (string) $attributes['created_at'] : null,
            roles: array_values(array_unique(array_map('strval', $roles))),
            permissions: array_values(array_unique(array_map('strval', $permissions))),
        );
    }
}
