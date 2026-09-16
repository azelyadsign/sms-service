<?php

namespace Azelya\SmsService\Tests\Laravel;

use Azelya\SmsService\Laravel\CacheTokenStore;
use Azelya\SmsService\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

final class CacheTokenStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Repository::class)) {
            $this->markTestSkipped('The Illuminate cache package is not installed.');
        }
    }

    public function test_save_load_forget_round_trip(): void
    {
        $store = new CacheTokenStore(new Repository(new ArrayStore()));

        $this->assertNull($store->load());

        $store->save('cached-token');

        $this->assertSame('cached-token', $store->load());

        $store->forget();

        $this->assertNull($store->load());
    }

    public function test_custom_key_and_ttl_are_respected(): void
    {
        $cache = new Repository(new ArrayStore());
        $store = new CacheTokenStore($cache, key: 'custom.key', ttl: 60);

        $store->save('cached-token');

        $this->assertSame('cached-token', $cache->get('custom.key'));
    }
}
