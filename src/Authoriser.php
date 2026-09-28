<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\Exception\InvalidPolicyException;
use Dirthara\Authorisation\Exception\AmbiguousPolicyException;
use Dirthara\Authorisation\Contract\Authoriser as AuthoriserContract;

use function count;

final readonly class Authoriser implements AuthoriserContract
{
    /**
     * @var list<Policy>
     */
    private array $policies;

    /**
     * @param iterable<mixed> $policies
     *
     * @throws InvalidPolicyException
     */
    public function __construct(
        iterable $policies,
        private DecisionStrategy $strategy = DecisionStrategy::OnlyOne,
    ) {
        $valid = [];

        // @mago-expect analysis:mixed-assignment Each value is checked to be a policy before it is kept
        foreach ($policies as $policy) {
            if (!$policy instanceof Policy) {
                throw InvalidPolicyException::forValue($policy, count($valid));
            }

            $valid[] = $policy;
        }

        $this->policies = $valid;
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
