<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Contract;

use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;

interface Policy
{
    public function authorise(AuthorisationContext $context): AuthorisationResult;
}
