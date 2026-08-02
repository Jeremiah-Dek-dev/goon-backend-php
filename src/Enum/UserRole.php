<?php

namespace App\Enum;

enum UserRole: string
{
    case USER = 'USER';
    case DRIVER = 'DRIVER';
    case ADMIN = 'ADMIN';
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN_MANAGER = 'ADMIN_MANAGER';
}
