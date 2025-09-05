<?php

namespace App\Enums;

enum UseRoleEnum: string
{
    case CUSTOMER = 'customer';
    case ADMIN = 'admin';
    case SUPERADMIN = 'superadmin';
    case SERVICE_PROVIDER = 'service-provider';
}
