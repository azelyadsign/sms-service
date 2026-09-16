<?php

namespace Azelya\SmsService\Dto;

use Azelya\SmsService\Enum\SmsStatus;

final class SmsLog extends Dto
{
    public function __construct(
        public readonly string $id,
        public readonly string $phone,
        public readonly string $message,
        public readonly string $direction,
        public readonly ?string $deviceType,
        public readonly string $status,
        public readonly ?string $externalId,
        public readonly ?string $rawResponse,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            direction: (string) ($data['direction'] ?? 'sent'),
            deviceType: isset($data['device_type']) ? (string) $data['device_type'] : null,
            status: (string) ($data['status'] ?? ''),
            externalId: isset($data['external_id']) ? (string) $data['external_id'] : null,
            rawResponse: isset($data['raw_response']) ? (string) $data['raw_response'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * The delivery is still being processed by the gateway.
     */
    public function isPending(): bool
    {
        return $this->status === SmsStatus::Pending->value;
    }

    /**
     * The delivery reached a terminal state (sent, delivered or failed).
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [
            SmsStatus::Sent->value,
            SmsStatus::Delivered->value,
            SmsStatus::Failed->value,
        ], true);
    }
}
