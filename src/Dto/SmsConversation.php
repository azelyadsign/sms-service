<?php

namespace Azelya\SmsService\Dto;

use Azelya\SmsService\Exception\InvalidResponseException;

/**
 * A sent SMS together with the replies it received (GET /sms/{id}/conversation).
 */
final class SmsConversation extends Dto
{
    /**
     * @param  SmsLog[]  $replies  Matched by external_id, oldest first. Empty when the phone never replied.
     */
    public function __construct(
        public readonly SmsLog $sms,
        public readonly array $replies = [],
    ) {
    }

    public static function fromArray(array $data): static
    {
        if (! is_array($data['sms'] ?? null)) {
            throw new InvalidResponseException('The SMS gateway did not return the conversation message.');
        }

        $replies = [];

        foreach (is_array($data['replies'] ?? null) ? $data['replies'] : [] as $reply) {
            if (is_array($reply)) {
                $replies[] = SmsLog::fromArray($reply);
            }
        }

        return new self(
            sms: SmsLog::fromArray($data['sms']),
            replies: $replies,
        );
    }

    public function toArray(): array
    {
        return [
            'sms' => $this->sms->toArray(),
            'replies' => array_map(static fn (SmsLog $reply): array => $reply->toArray(), $this->replies),
        ];
    }
}
