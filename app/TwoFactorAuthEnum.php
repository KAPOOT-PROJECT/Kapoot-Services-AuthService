<?php

namespace App;

enum TwoFactorAuthEnum: string
{
    case DISABLED = 'disabled';
    case MOBILE = 'mobile';
    case EMAIL = 'email';
}
