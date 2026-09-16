<?php

namespace Azelya\SmsService\Http;

/**
 * How a request authenticates against the gateway.
 */
enum AuthMode: string
{
    /**
     * No authentication header (login/register endpoints).
     */
    case None = 'none';

    /**
     * Passport access token in the Authorization header (API consumers).
     */
    case Bearer = 'bearer';

    /**
     * Static device token in the X-Device-Token header (gateway devices).
     */
    case Device = 'device';
}
