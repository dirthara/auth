<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests;

use Error;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\PolicyDecision;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationStatus;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;

final class PolicyDecisionTest extends TestCase
{
    #[Test]
    public function it_records_a_policy_that_allowed(): void
    {
        $policy = new FixedPolicy(AuthorisationResult::allowed());

        $decision = PolicyDecision::allowed($policy);

        self::assertSame($policy, $decision->policy);
        self::assertSame(AuthorisationStatus::Allowed, $decision->status);
        self::assertNull($decision->denial);
        self::assertTrue($decision->isAllowed());
        self::assertFalse($decision->isDenied());
    }

    #[Test]
    public function it_records_a_policy_that_denied_with_its_denial(): void
    {
        $policy = new FixedPolicy(AuthorisationResult::denied());
        $denial = new AuthorisationDenial('You do not own this resource.');

        $decision = PolicyDecision::denied($policy, $denial);

        self::assertSame($policy, $decision->policy);
        self::assertSame(AuthorisationStatus::Denied, $decision->status);
        self::assertSame($denial, $decision->denial);
        self::assertFalse($decision->isAllowed());
        self::assertTrue($decision->isDenied());
    }

    #[Test]
    public function it_records_a_policy_that_denied_without_a_reason(): void
    {
        self::assertNull(PolicyDecision::denied(new FixedPolicy(AuthorisationResult::denied()))->denial);
    }

    #[Test]
    public function it_cannot_be_changed(): void
    {
        $decision = PolicyDecision::allowed(new FixedPolicy(AuthorisationResult::allowed()));

        $this->expectException(Error::class);

        // @mago-expect analysis:invalid-property-write Writing the status is the point of the test
        $decision->status = AuthorisationStatus::Denied;
    }
}
