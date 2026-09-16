<?php

namespace Azelya\SmsService\Dto;

/**
 * A page of the user's SMS logs (GET /sms).
 *
 * The gateway returns a plain Laravel paginator here — unlike the JSON:API
 * collections of the admin endpoints. Pagination data sits at the top level
 * (total, current_page, next_page_url, ...) with no "meta" key; this DTO
 * synthesizes a meta array from those keys so it exposes the same helpers
 * as the other paginated DTOs.
 */
final class PaginatedSms extends Dto
{
    /**
     * @param  SmsLog[]  $sms
     * @param  array<int, array{url: string|null, label: string, active: bool}>  $links  Raw Laravel paginator links.
     * @param  array<string, mixed>  $meta  Synthesized from the paginator's top-level keys.
     */
    public function __construct(
        public readonly array $sms,
        public readonly array $links = [],
        public readonly array $meta = [],
    ) {
    }

    public static function fromArray(array $data): static
    {
        $sms = [];

        foreach (is_array($data['data'] ?? null) ? $data['data'] : [] as $item) {
            if (is_array($item)) {
                $sms[] = SmsLog::fromArray($item);
            }
        }

        return new self(
            sms: $sms,
            links: is_array($data['links'] ?? null) ? $data['links'] : [],
            meta: [
                'total' => (int) ($data['total'] ?? count($sms)),
                'current_page' => (int) ($data['current_page'] ?? 1),
                'last_page' => (int) ($data['last_page'] ?? 1),
                'per_page' => (int) ($data['per_page'] ?? count($sms)),
                'next_page_url' => isset($data['next_page_url']) ? (string) $data['next_page_url'] : null,
                'prev_page_url' => isset($data['prev_page_url']) ? (string) $data['prev_page_url'] : null,
            ],
        );
    }

    public function toArray(): array
    {
        return [
            'sms' => array_map(static fn (SmsLog $log): array => $log->toArray(), $this->sms),
            'links' => $this->links,
            'meta' => $this->meta,
        ];
    }

    public function total(): int
    {
        return (int) ($this->meta['total'] ?? count($this->sms));
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
        return (int) ($this->meta['per_page'] ?? count($this->sms));
    }

    public function nextPage(): ?string
    {
        return isset($this->meta['next_page_url']) ? (string) $this->meta['next_page_url'] : null;
    }

    public function prevPage(): ?string
    {
        return isset($this->meta['prev_page_url']) ? (string) $this->meta['prev_page_url'] : null;
    }
}
