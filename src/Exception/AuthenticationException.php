<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown on 401 responses: invalid credentials or an invalid/inactive device token.
 */
class AuthenticationException extends SmsGatewayException
{
}
