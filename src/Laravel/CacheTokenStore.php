<?php

namespace Azelya\SmsService\Laravel;

use Azelya\SmsService\TokenStore\TokenStore;
use Illuminate\Contracts\Cache\Repository;

/**
 * TokenStore backed by a Laravel cache repository. The 401-recovery flow in
 * the ApiClient is authoritative for stale tokens, so the TTL only exists for
 * cache hygiene.
 */
final class CacheTokenStore implements TokenStore
{
    public function __construct(
        private readonly Repository $cache,
        private readonly string $key = 'azelya-sms.token',
        private readonly ?int $ttl = null,
    ) {
    }

    public function save(string $token): void
    {
        if ($this->ttl === null) {
            $this->cache->forever($this->key, $token);

            return;
        }

        $this->cache->put($this->key, $token, $this->ttl);
    }

    public function load(): ?string
    {
        $token = $this->cache->get($this->key);

        return is_string($token) ? $token : null;
    }

    public function forget(): void
    {
        $this->cache->forget($this->key);
    }
}
