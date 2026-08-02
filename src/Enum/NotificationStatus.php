<?php

namespace App\Enum;

enum NotificationStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case DELAYED = 'DELAYED';
    case SKIPPED = 'SKIPPED';
}
