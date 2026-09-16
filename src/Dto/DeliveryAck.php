<?php

namespace Azelya\SmsService\Dto;

/**
 * Acknowledgment returned by send/retry/reply/status endpoints.
 */
final class DeliveryAck extends Dto
{
    public function __construct(
        public readonly string $message,
        public readonly string $smsLogId,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(
            message: (string) ($data['message'] ?? ''),
            smsLogId: (string) ($data['sms_log_id'] ?? ''),
        );
    }
}
