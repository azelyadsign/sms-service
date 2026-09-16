<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown on 429 responses (too many attempts / throttle exceeded).
 */
class RateLimitException extends SmsGatewayException
{
    /**
     * Seconds to wait before retrying, parsed from the Retry-After or
     * X-RateLimit-Reset headers. Null when the gateway sent neither.
     */
    public function retryAfter(): ?int
    {
        return isset($this->context['retry_after']) ? (int) $this->context['retry_after'] : null;
    }
}
