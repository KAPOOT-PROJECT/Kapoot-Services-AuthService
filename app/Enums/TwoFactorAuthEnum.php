<?php

namespace App\Enums;

enum TwoFactorAuthEnum: string
{
    case DISABLED = 'disabled';
    case MOBILE = 'mobile';
    case EMAIL = 'email';
}
