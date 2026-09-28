<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\Exception\AmbiguousPolicyException;
use Dirthara\Authorisation\Contract\Authoriser as AuthoriserContract;

use function iterator_to_array;

final readonly class Authoriser implements AuthoriserContract
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

        if ($result === null || $decidedBy === null) {
            return AuthorisationResult::notApplicable();
        }

        return $result->withPolicy($decidedBy);
    }
}
