<?php

namespace Azelya\SmsService;

use Azelya\SmsService\Admin\AdminService;
use Azelya\SmsService\Auth\AuthService;
use Azelya\SmsService\Device\DeviceGatewayService;
use Azelya\SmsService\Device\UserDeviceService;
use Azelya\SmsService\Http\ApiClient;
use Azelya\SmsService\Sms\SmsService;
use Azelya\SmsService\TokenStore\FileTokenStore;
use Azelya\SmsService\TokenStore\TokenStore;
use Azelya\SmsService\User\UserService;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;

/**
 * Entry point of the SDK. Build one client per gateway and reach the API
 * through the resource accessors:
 *
 *     $client->auth()->login(...);
 *     $client->sms()->send(...);
 *     $client->devices()->create(...);
 *     $client->admin()->approve(...);
 *     $client->deviceGateway()->updateStatus(...);
 */
final class SmsGatewayClient
{
    private readonly ApiClient $api;

    private readonly AuthService $auth;

    private readonly UserService $user;

    private readonly SmsService $sms;

    private readonly UserDeviceService $devices;

    private readonly DeviceGatewayService $deviceGateway;

    private readonly AdminService $admin;

    public function __construct(
        Config|array $config,
        ?TokenStore $tokenStore = null,
        ?ClientInterface $httpClient = null,
    ) {
        $config = $config instanceof Config ? $config : Config::fromArray($config);

        $this->api = new ApiClient(
            http: $httpClient ?? new Client([
                'timeout' => $config->timeout,
                'connect_timeout' => $config->connectTimeout,
                'verify' => $config->verify,
                'http_errors' => false,
            ]),
            config: $config,
            tokenStore: $tokenStore ?? new FileTokenStore(),
        );

        $this->auth = new AuthService($this->api);
        $this->user = new UserService($this->api);
        $this->sms = new SmsService($this->api);
        $this->devices = new UserDeviceService($this->api);
        $this->deviceGateway = new DeviceGatewayService($this->api);
        $this->admin = new AdminService($this->api);
    }

    public function auth(): AuthService
    {
        return $this->auth;
    }

    public function user(): UserService
    {
        return $this->user;
    }

    public function sms(): SmsService
    {
        return $this->sms;
    }

    /**
     * The authenticated user's device CRUD (bearer auth).
     */
    public function devices(): UserDeviceService
    {
        return $this->devices;
    }

    /**
     * Gateway device endpoints (X-Device-Token auth).
     */
    public function deviceGateway(): DeviceGatewayService
    {
        return $this->deviceGateway;
    }

    public function admin(): AdminService
    {
        return $this->admin;
    }

    public function getToken(): ?string
    {
        return $this->api->getToken();
    }

    public function setToken(string $token): void
    {
        $this->api->setToken($token);
    }

    public function forgetToken(): void
    {
        $this->api->forgetToken();
    }
}
