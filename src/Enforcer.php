<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

use Dirthara\Authorisation\Contract\Authoriser;
use Dirthara\Authorisation\Exception\AuthorisationDeniedException;

final readonly class Enforcer
{
    public function __construct(
        private Authoriser $authoriser,
    ) {}

    /**
     * @throws AuthorisationDeniedException
     */
    public function ensure(AuthorisationContext $context): AuthorisationResult
    {
        $result = $this->authoriser->authorise($context);

        if ($result->isAllowed()) {
            return $result;
        }

        if ($result->isDenied()) {
            throw AuthorisationDeniedException::denied($context, $result);
        }

        throw AuthorisationDeniedException::noPolicyApplies($context, $result);
    }

    public function allows(AuthorisationContext $context): bool
    {
        return $this->authoriser->authorise($context)->isAllowed();
    }
}
