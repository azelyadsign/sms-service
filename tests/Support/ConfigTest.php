<?php

namespace Azelya\SmsService\Tests\Support;

use Azelya\SmsService\Config;
use Azelya\SmsService\Tests\TestCase;
use InvalidArgumentException;

final class ConfigTest extends TestCase
{
    public function test_defaults(): void
    {
        $config = new Config('https://smsgate.test/api/v1');

        $this->assertSame('https://smsgate.test/api/v1', $config->baseUrl);
        $this->assertNull($config->email);
        $this->assertNull($config->password);
        $this->assertNull($config->appEmail);
        $this->assertNull($config->appPassword);
        $this->assertNull($config->token);
        $this->assertNull($config->deviceToken);
        $this->assertSame(30.0, $config->timeout);
        $this->assertSame(10.0, $config->connectTimeout);
        $this->assertTrue($config->verify);
        $this->assertTrue($config->retryOnUnauthorized);
        $this->assertNull($config->credentials());
    }

    public function test_empty_base_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Config('');
    }

    public function test_from_array_reads_snake_case_keys_and_casts_scalars(): void
    {
        $config = Config::fromArray([
            'base_url' => 'https://gateway.example.com/api/v1',
            'email' => 'user@example.com',
            'password' => 'secret',
            'timeout' => '15',
            'connect_timeout' => '5',
            'verify' => false,
            'retry_on_unauthorized' => false,
        ]);

        $this->assertSame('https://gateway.example.com/api/v1', $config->baseUrl);
        $this->assertSame('user@example.com', $config->email);
        $this->assertSame('secret', $config->password);
        $this->assertSame(15.0, $config->timeout);
        $this->assertSame(5.0, $config->connectTimeout);
        $this->assertFalse($config->verify);
        $this->assertFalse($config->retryOnUnauthorized);
    }

    public function test_from_array_requires_base_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base_url');

        Config::fromArray(['email' => 'user@example.com']);
    }

    public function test_app_credentials_take_precedence_over_user_credentials(): void
    {
        $config = new Config(
            'https://smsgate.test/api/v1',
            email: 'user@example.com',
            password: 'user-secret',
            appEmail: 'app@example.com',
            appPassword: 'app-secret',
        );

        $this->assertSame([
            'email' => 'app@example.com',
            'password' => 'app-secret',
            'app' => true,
        ], $config->credentials());
    }

    public function test_user_credentials_are_used_when_app_credentials_are_absent(): void
    {
        $config = new Config(
            'https://smsgate.test/api/v1',
            email: 'user@example.com',
            password: 'user-secret',
        );

        $this->assertSame([
            'email' => 'user@example.com',
            'password' => 'user-secret',
            'app' => false,
        ], $config->credentials());
    }

    public function test_credentials_are_null_without_complete_pairs(): void
    {
        $config = new Config('https://smsgate.test/api/v1', email: 'user@example.com');

        $this->assertNull($config->credentials());
    }

    public function test_to_array_round_trip(): void
    {
        $config = new Config(
            'https://smsgate.test/api/v1',
            email: 'user@example.com',
            password: 'secret',
            deviceToken: 'device-secret',
            timeout: 5.0,
        );

        $this->assertEquals($config, Config::fromArray($config->toArray()));
    }
}
