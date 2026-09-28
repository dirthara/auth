<?php

declare(strict_types=1);

namespace Dirthara\Auth;

use Dirthara\Auth\Contract\Policy;
use Dirthara\Auth\Exception\AmbiguousPolicyException;

use function iterator_to_array;

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
        $this->policies = iterator_to_array($policies, preserve_keys: false);
    }

    public function authorise(AuthorisationContext $context): AuthorisationResult
    {
        $result = null;
        $decidedBy = null;

        foreach ($this->policies as $policy) {
            $policyResult = $policy->authorise($context);

            if ($policyResult->isNotApplicable()) {
                continue;
            }

            if ($decidedBy !== null) {
                throw AmbiguousPolicyException::forContext($context, $decidedBy, $policy);
            }

            $result = $policyResult;
            $decidedBy = $policy;
        }

        return $result ?? AuthorisationResult::notApplicable();
    }
}
