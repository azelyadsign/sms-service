<?php

namespace Azelya\SmsService\Dto;

final class DeviceAuth extends Dto
{
    /**
     * @param  string  $auth  The "<app-key>:<signature>" string returned by the broadcasting auth endpoint.
     */
    public function __construct(public readonly string $auth)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self(auth: (string) ($data['auth'] ?? ''));
    }

    /**
     * The app key part, before the colon.
     */
    public function key(): string
    {
        return explode(':', $this->auth, 2)[0] ?? '';
    }

    /**
     * The HMAC signature part, after the colon.
     */
    public function signature(): string
    {
        return explode(':', $this->auth, 2)[1] ?? '';
    }
}
