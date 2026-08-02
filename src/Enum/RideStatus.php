<?php

namespace App\Enum;

enum RideStatus: string
{
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case PARTIALLY_APPROVED = 'PARTIALLY_APPROVED';
    case DECLINED = 'DECLINED';
    case SCHEDULED = 'SCHEDULED';
    case ASSIGNED = 'ASSIGNED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case DRIVER_EN_ROUTE = 'DRIVER_EN_ROUTE';
    case ARRIVED = 'ARRIVED';
    case CANCELLED = 'CANCELLED';
}
