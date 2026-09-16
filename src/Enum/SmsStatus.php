<?php

namespace Azelya\SmsService\Enum;

/**
 * Statuses reported by the SMS gateway. The gateway treats these as free-form
 * strings, so the SDK compares against the raw values below.
 */
enum SmsStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Received = 'received';
}
