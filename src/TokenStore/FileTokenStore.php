<?php

namespace Azelya\SmsService\TokenStore;

/**
 * Default store: a plain file in the system temp directory (or a custom path).
 * The token itself is not a secret worth encrypting at rest, but permissions
 * are restricted to the current user where the platform supports it.
 */
final class FileTokenStore implements TokenStore
{
    private readonly string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? sys_get_temp_dir().DIRECTORY_SEPARATOR.'azelya-sms.token';
    }

    public function save(string $token): void
    {
        file_put_contents($this->path, $token, LOCK_EX);
        @chmod($this->path, 0600);
    }

    public function load(): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }

        $token = file_get_contents($this->path);

        if ($token === false || trim($token) === '') {
            return null;
        }

        return trim($token);
    }

    public function forget(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
