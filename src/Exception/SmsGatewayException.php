<?php

namespace Azelya\SmsService\Exception;

use RuntimeException;

/**
 * Base exception for every error raised by the SDK.
 */
class SmsGatewayException extends RuntimeException
{
    private readonly int $status;

    /**
     * @param  array<string, mixed>  $context  Additional details, e.g. validation errors or the failing SMS id.
     */
    public function __construct(
        string $message = '',
        int $status = 0,
        ?\Throwable $previous = null,
        public readonly array $context = [],
    ) {
        $this->status = $status;

        parent::__construct($message, 0, $previous);
    }

    /**
     * The HTTP status code that caused the error (0 for local errors).
     */
    public function statusCode(): int
    {
        return $this->status;
    }
}
