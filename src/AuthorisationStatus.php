<?php

declare(strict_types=1);

namespace Dirthara\Auth;

enum AuthorisationStatus
{
    case Allowed;
    case Denied;
    case NotApplicable;
}
