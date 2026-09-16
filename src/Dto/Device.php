<?php

namespace Azelya\SmsService\Dto;

final class Device extends Dto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $token,
        public readonly bool $isActive,
        public readonly ?string $createdAt,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            token: (string) ($data['token'] ?? ''),
            isActive: (bool) ($data['is_active'] ?? false),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }

    /**
     * Build the device from a JSON:API resource object
     * ({data: {id, type, attributes: {...}}}) or from its flat attributes.
     *
     * @param  array<string, mixed>  $resource
     */
    public static function fromResource(array $resource): static
    {
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : $resource;

        return self::fromArray($attributes);
    }
}
