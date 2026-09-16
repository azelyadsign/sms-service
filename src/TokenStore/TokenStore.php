<?php

namespace Azelya\SmsService\TokenStore;

/**
 * Persistence for the gateway access token, so clients only log in once per
 * process/machine. Implementations must be cheap and safe to call on every
 * request.
 */
interface TokenStore
{
    public function save(string $token): void;

    public function load(): ?string;

    public function forget(): void;
}
