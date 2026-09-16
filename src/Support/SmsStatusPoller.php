<?php

namespace Azelya\SmsService\Support;

use Azelya\SmsService\Dto\SmsLog;
use Azelya\SmsService\Exception\SmsTimeoutException;
use Azelya\SmsService\Sms\SmsService;

/**
 * Polls GET /sms/{id} until the delivery leaves the pending state.
 */
final class SmsStatusPoller
{
    private const NANOS_PER_SECOND = 1_000_000_000;

    private const MICROS_PER_SECOND = 1_000_000;

    public function __construct(private readonly SmsService $sms)
    {
    }

    /**
     * @param  callable(SmsLog): void|null  $onStatusChange  Invoked on each observed status transition.
     */
    public function wait(
        string $smsLogId,
        int $timeoutSeconds = 120,
        float $pollIntervalSeconds = 2.0,
        ?callable $onStatusChange = null,
    ): SmsLog {
        $deadline = hrtime(true) + max(1, $timeoutSeconds) * self::NANOS_PER_SECOND;
        $pollInterval = max(100_000, (int) ($pollIntervalSeconds * self::MICROS_PER_SECOND));
        $lastStatus = null;

        while (true) {
            $log = $this->sms->get($smsLogId);

            if ($lastStatus !== null && $onStatusChange !== null && $log->status !== $lastStatus) {
                $onStatusChange($log);
            }

            $lastStatus = $log->status;

            if (! $log->isPending()) {
                return $log;
            }

            if (hrtime(true) >= $deadline) {
                throw new SmsTimeoutException(
                    sprintf(
                        'Timed out after %d seconds waiting for SMS %s to leave the pending state (last status: %s).',
                        $timeoutSeconds,
                        $smsLogId,
                        $log->status,
                    ),
                    context: [
                        'sms_log_id' => $smsLogId,
                        'last_status' => $log->status,
                    ],
                );
            }

            usleep($pollInterval);
        }
    }
}
