<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown on 409 responses, e.g. approving an already approved user.
 */
class ConflictException extends SmsGatewayException
{
}
