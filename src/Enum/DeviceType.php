<?php

namespace Azelya\SmsService\Enum;

/**
 * Device types accepted by the gateway for SMS routing and device registration.
 */
enum DeviceType: string
{
    case Android = 'android';
    case Iot = 'iot';
}
