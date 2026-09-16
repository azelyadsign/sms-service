<?php

namespace Azelya\SmsService\TokenStore;

/**
 * In-memory store. Suitable for long-running workers and queues that do not
 * want to touch the filesystem. The token is lost when the process ends.
 */
final class ArrayTokenStore implements TokenStore
{
    private ?string $token = null;

    public function save(string $token): void
    {
        $this->token = $token;
    }

    public function load(): ?string
    {
        return $this->token;
    }

    public function forget(): void
    {
        $this->token = null;
    }
}
