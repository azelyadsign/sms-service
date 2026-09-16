<?php

namespace Azelya\SmsService\Laravel\Facades;

use Azelya\SmsService\SmsGatewayClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Azelya\SmsService\Auth\AuthService auth()
 * @method static \Azelya\SmsService\User\UserService user()
 * @method static \Azelya\SmsService\Sms\SmsService sms()
 * @method static \Azelya\SmsService\Device\UserDeviceService devices()
 * @method static \Azelya\SmsService\Device\DeviceGatewayService deviceGateway()
 * @method static \Azelya\SmsService\Admin\AdminService admin()
 * @method static string|null getToken()
 * @method static void setToken(string $token)
 * @method static void forgetToken()
 *
 * @see SmsGatewayClient
 */
final class AzelyaSms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsGatewayClient::class;
    }
}
