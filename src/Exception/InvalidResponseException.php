<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown when the gateway returned a body the SDK could not understand
 * (invalid JSON or an unexpected structure).
 */
class InvalidResponseException extends SmsGatewayException
{
}
