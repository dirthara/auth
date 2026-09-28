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
    public function __construct(
        iterable $policies,
        private DecisionStrategy $strategy = DecisionStrategy::OnlyOne,
    ) {
        $this->policies = iterator_to_array($policies, preserve_keys: false);
    }

    public function authorise(AuthorisationContext $context): AuthorisationResult
    {
        $applicable = null;
        $allowed = null;
        $denied = null;

        foreach ($this->policies as $policy) {
            $policyResult = $policy->authorise($context);

            if ($policyResult->isNotApplicable()) {
                continue;
            }

            if ($applicable !== null && $this->strategy === DecisionStrategy::OnlyOne) {
                throw AmbiguousPolicyException::forContext($context, $applicable, $policy);
            }

            $applicable ??= $policy;

            if ($policyResult->isAllowed()) {
                $allowed ??= $policyResult->withPolicy($policy);
            } else {
                $denied ??= $policyResult->withPolicy($policy);
            }
        }

        $result = match ($this->strategy) {
            DecisionStrategy::OnlyOne, DecisionStrategy::AtLeastOne => $allowed ?? $denied,
            DecisionStrategy::All => $denied ?? $allowed,
        };

        return ($result ?? AuthorisationResult::notApplicable())->withConsulted(...$this->policies);
    }
}
