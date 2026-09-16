<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown on 403 responses: missing permission/role or accessing another user's SMS log.
 */
class PermissionDeniedException extends SmsGatewayException
{
}
