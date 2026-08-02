<?php

namespace App\Enum;

enum DriverStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
