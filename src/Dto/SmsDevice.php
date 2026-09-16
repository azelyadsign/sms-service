<?php

namespace Azelya\SmsService\Dto;

/**
 * The device that handled an SMS, as nested by the gateway inside list and
 * conversation responses ({id, name, type} only — never JSON:API here).
 */
final class SmsDevice extends Dto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? ''),
        );
    }
}
