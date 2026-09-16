<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown when the gateway could not be reached (connection refused, timeout,
 * DNS failure, too many redirects, ...).
 */
class NetworkException extends SmsGatewayException
{
}
