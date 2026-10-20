<?php

namespace App\Enums;

enum VisibilityMode: string
{
    case PUBLIC = 'public';
    case AUTH = 'auth';
    case RESTRICTED = 'restricted';
}
