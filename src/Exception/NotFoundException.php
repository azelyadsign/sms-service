<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown on 404 responses, e.g. an unknown SMS log id or no registered device.
 */
class NotFoundException extends SmsGatewayException
{
}
