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
        $decisions = [];
        $allowed = null;
        $denied = null;

        foreach ($this->policies as $policy) {
            $policyResult = $policy->authorise($context);

            if ($policyResult->isNotApplicable()) {
                continue;
            }

            if ($decisions !== [] && $this->strategy === DecisionStrategy::OnlyOne) {
                throw AmbiguousPolicyException::forContext($context, $decisions[0]->policy, $policy);
            }

            if ($policyResult->isAllowed()) {
                $decisions[] = PolicyDecision::allowed($policy);
                $allowed ??= $policyResult->withPolicy($policy);

                continue;
            }

            $decisions[] = PolicyDecision::denied($policy, $policyResult->denial);
            $denied ??= $policyResult->withPolicy($policy);
        }

        $result = match ($this->strategy) {
            DecisionStrategy::OnlyOne => $allowed ?? $denied,
            DecisionStrategy::AtLeastOne => $allowed ?? ($denied === null ? null : AuthorisationResult::denied()),
            DecisionStrategy::All => $denied ?? ($allowed === null ? null : AuthorisationResult::allowed()),
        };

        return ($result ?? AuthorisationResult::notApplicable())
            ->withDecisions(...$decisions)
            ->withConsulted(...$this->policies);
    }
}
