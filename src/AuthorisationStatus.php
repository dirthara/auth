<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

enum AuthorisationStatus
{
    case Allowed;
    case Denied;
    case NotApplicable;
}
