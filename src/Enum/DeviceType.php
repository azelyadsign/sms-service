<?php

namespace Azelya\SmsService\Enum;

/**
 * Common device types for SMS routing and device registration.
 *
 * The gateway accepts any alpha_dash string of at least 3 characters
 * ('ios', 'galaxy-s22', ...) — this enum only covers the common values and
 * is interchangeable with a plain string in every SDK signature.
 */
enum DeviceType: string
{
    case Android = 'android';
    case Iot = 'iot';
}
