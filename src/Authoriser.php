<?php

declare(strict_types=1);

namespace Dirthara\Auth;

use Dirthara\Auth\Contract\Policy;
use Dirthara\Auth\Exception\AmbiguousPolicyException;

final readonly class Authoriser
{
    /**
     * @var list<Policy>
     */
    private array $policies;

    /**
     * @param iterable<Policy> $policies
     */
    public function __construct(iterable $policies)
    {
        $this->policies = [...$policies];
    }

    public function authorise(AuthorisationContext $context): AuthorisationResult
    {
        $result = null;

        foreach ($this->policies as $policy) {
            $policyResult = $policy->authorise($context);

            if ($policyResult->isNotApplicable()) {
                continue;
            }

            if ($result !== null) {
                throw AmbiguousPolicyException::forContext($context);
            }

            $result = $policyResult;
        }

        return $result ?? AuthorisationResult::notApplicable();
    }
}
