<?php

declare(strict_types=1);

namespace Dirthara\Auth;

use UnitEnum;

final readonly class AuthorisationContext
{
    public function __construct(
        public object $actor,
        public string|UnitEnum $ability,
        public mixed $subject = null,
    ) {}
}
