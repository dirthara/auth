<?php

declare(strict_types=1);

namespace Dirthara\Auth\Contract;

use Dirthara\Auth\AuthorisationResult;
use Dirthara\Auth\AuthorisationContext;

interface Policy
{
    public function authorise(AuthorisationContext $context): AuthorisationResult;
}
