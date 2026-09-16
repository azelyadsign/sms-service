<?php

namespace Azelya\SmsService\Dto;

final class PaginatedUsers extends Dto
{
    /**
     * @param  User[]  $users
     * @param  array<string, mixed>  $links
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $users,
        public readonly array $links = [],
        public readonly array $meta = [],
    ) {
    }

    public static function fromArray(array $data): static
    {
        $users = array_map(
            static fn (array $resource): User => User::fromResource($resource),
            is_array($data['data'] ?? null) ? $data['data'] : [],
        );

        return new self(
            users: $users,
            links: is_array($data['links'] ?? null) ? $data['links'] : [],
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'users' => array_map(static fn (User $user): array => $user->toArray(), $this->users),
            'links' => $this->links,
            'meta' => $this->meta,
        ];
    }

    public function total(): int
    {
        return (int) ($this->meta['total'] ?? count($this->users));
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
        return (int) ($this->meta['per_page'] ?? count($this->users));
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
