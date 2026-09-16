<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown by the status poller when the SMS did not leave the pending state
 * within the configured timeout. The last known status is available in the
 * exception context.
 */
class SmsTimeoutException extends SmsGatewayException
{
    /**
     * The last status observed before the timeout.
     */
    public function lastStatus(): ?string
    {
        return isset($this->context['last_status']) ? (string) $this->context['last_status'] : null;
    }
}
