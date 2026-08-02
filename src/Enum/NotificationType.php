<?php

namespace App\Enum;

enum NotificationType: string
{
    case RIDE_RESPONSE = 'RIDE_RESPONSE';
    case GLOBAL_UPDATE = 'GLOBAL_UPDATE';
    case LOGIN = 'LOGIN';
    case REGISTER = 'REGISTER';
    case PROMO = 'PROMO';
    case SYSTEM = 'SYSTEM';
    case RIDE_REQUEST = 'RIDE_REQUEST';
    case PUSH_TO_DRIVERS = 'PUSH_TO_DRIVERS';
    case PUSH_TO_USERS = 'PUSH_TO_USERS';
    case PUSH_TO_CUSTOMERS = 'PUSH_TO_CUSTOMERS';
    case PUSH_PROMO = 'PUSH_PROMO';
    case PUSH_NEW_DRIVER = 'PUSH_NEW_DRIVER';
    case PUSH_NEW_USER = 'PUSH_NEW_USER';
    case PUSH_TO_ADMINS = 'PUSH_TO_ADMINS';
}
