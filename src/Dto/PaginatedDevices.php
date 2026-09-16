<?php

namespace Azelya\SmsService\Dto;

/**
 * A page of devices (GET /admin/devices), returned as a JSON:API collection
 * ({data: [...], links, meta}) — mirrors PaginatedUsers.
 */
final class PaginatedDevices extends Dto
{
    /**
     * @param  Device[]  $devices
     * @param  array<string, mixed>  $links
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $devices,
        public readonly array $links = [],
        public readonly array $meta = [],
    ) {
    }

    public static function fromArray(array $data): static
    {
        $devices = [];

        foreach (is_array($data['data'] ?? null) ? $data['data'] : [] as $resource) {
            if (is_array($resource)) {
                $devices[] = Device::fromResource($resource);
            }
        }

        return new self(
            devices: $devices,
            links: is_array($data['links'] ?? null) ? $data['links'] : [],
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'devices' => array_map(static fn (Device $device): array => $device->toArray(), $this->devices),
            'links' => $this->links,
            'meta' => $this->meta,
        ];
    }

    public function total(): int
    {
        return (int) ($this->meta['total'] ?? count($this->devices));
    }

    public function currentPage(): int
    {
        return (int) ($this->meta['current_page'] ?? 1);
    }

    public function lastPage(): int
    {
        return (int) ($this->meta['last_page'] ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->meta['per_page'] ?? count($this->devices));
    }

    public function nextPage(): ?string
    {
        return isset($this->links['next']) ? (string) $this->links['next'] : null;
    }

    public function prevPage(): ?string
    {
        return isset($this->links['prev']) ? (string) $this->links['prev'] : null;
    }
}
