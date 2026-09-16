<?php

namespace Azelya\SmsService\Tests\TokenStore;

use Azelya\SmsService\Tests\TestCase;
use Azelya\SmsService\TokenStore\FileTokenStore;

final class FileTokenStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'azelya-sms-test-'.bin2hex(random_bytes(8)).'.token';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_missing_file_loads_null(): void
    {
        $this->assertNull((new FileTokenStore($this->path))->load());
    }

    public function test_save_and_load_round_trip(): void
    {
        $store = new FileTokenStore($this->path);

        $store->save('token-abc');

        $this->assertSame('token-abc', $store->load());
    }

    public function test_save_overwrites_the_previous_token(): void
    {
        $store = new FileTokenStore($this->path);

        $store->save('first-token');
        $store->save('second-token');

        $this->assertSame('second-token', $store->load());
    }

    public function test_forget_removes_the_file(): void
    {
        $store = new FileTokenStore($this->path);

        $store->save('token-abc');
        $store->forget();

        $this->assertNull($store->load());
        $this->assertFileDoesNotExist($this->path);
    }

    public function test_forget_is_idempotent(): void
    {
        $store = new FileTokenStore($this->path);

        $store->forget();
        $store->forget();

        $this->assertNull($store->load());
    }
}
