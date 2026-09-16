<?php

namespace Azelya\SmsService\Laravel;

use Azelya\SmsService\Config;
use Azelya\SmsService\SmsGatewayClient;
use Azelya\SmsService\TokenStore\ArrayTokenStore;
use Illuminate\Support\ServiceProvider;

final class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/azelya-sms.php', 'azelya-sms');

        $this->app->singleton(SmsGatewayClient::class, function ($app): SmsGatewayClient {
            $config = (array) $app['config']->get('azelya-sms', []);

            $tokenStore = isset($app['cache.store'])
                ? new CacheTokenStore($app['cache.store'], ttl: isset($config['token_ttl']) ? (int) $config['token_ttl'] : null)
                : new ArrayTokenStore();

            return new SmsGatewayClient(
                config: Config::fromArray($config),
                tokenStore: $tokenStore,
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/azelya-sms.php' => $this->app->configPath('azelya-sms.php'),
        ], 'azelya-sms');
    }
}
