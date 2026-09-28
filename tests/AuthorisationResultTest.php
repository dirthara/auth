<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationStatus;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
use Dirthara\Authorisation\Exception\NotApplicableResultException;

final class AuthorisationResultTest extends TestCase
{
    #[Test]
    public function it_allows(): void
    {
        $result = AuthorisationResult::allowed();

        self::assertSame(AuthorisationStatus::Allowed, $result->status);
        self::assertNull($result->policy);
        self::assertNull($result->denial);
        self::assertTrue($result->isAllowed());
        self::assertFalse($result->isDenied());
        self::assertFalse($result->isNotApplicable());
    }

    #[Test]
    public function it_denies(): void
    {
        $result = AuthorisationResult::denied();

        self::assertSame(AuthorisationStatus::Denied, $result->status);
        self::assertNull($result->policy);
        self::assertNull($result->denial);
        self::assertFalse($result->isAllowed());
        self::assertTrue($result->isDenied());
        self::assertFalse($result->isNotApplicable());
    }

    #[Test]
    public function it_does_not_apply(): void
    {
        $result = AuthorisationResult::notApplicable();

        self::assertSame(AuthorisationStatus::NotApplicable, $result->status);
        self::assertNull($result->policy);
        self::assertNull($result->denial);
        self::assertFalse($result->isAllowed());
        self::assertFalse($result->isDenied());
        self::assertTrue($result->isNotApplicable());
    }

    #[Test]
    public function it_refuses_to_attach_a_policy_to_a_result_that_does_not_apply(): void
    {
        $this->expectException(NotApplicableResultException::class);

        AuthorisationResult::notApplicable()->withPolicy(new FixedPolicy(AuthorisationResult::allowed()));
    }

    #[Test]
    public function it_attaches_a_policy_to_an_allowed_result_as_a_new_result(): void
    {
        $result = AuthorisationResult::allowed();
        $policy = new FixedPolicy($result);

        $withPolicy = $result->withPolicy($policy);

        self::assertNotSame($result, $withPolicy);
        self::assertTrue($withPolicy->isAllowed());
        self::assertSame($policy, $withPolicy->policy);
        self::assertTrue($result->isAllowed());
        self::assertNull($result->policy);
    }

    #[Test]
    public function it_attaches_a_policy_to_a_denied_result_as_a_new_result(): void
    {
        $result = AuthorisationResult::denied();
        $policy = new FixedPolicy($result);

        $withPolicy = $result->withPolicy($policy);

        self::assertNotSame($result, $withPolicy);
        self::assertTrue($withPolicy->isDenied());
        self::assertSame($policy, $withPolicy->policy);
        self::assertTrue($result->isDenied());
        self::assertNull($result->policy);
    }

    #[Test]
    public function it_keeps_the_exact_policy_instance(): void
    {
        $result = AuthorisationResult::allowed();
        $policy = new FixedPolicy($result);
        $otherPolicy = new FixedPolicy($result);

        $withPolicy = $result->withPolicy($policy);

        self::assertSame($policy, $withPolicy->policy);
        self::assertNotSame($otherPolicy, $withPolicy->policy);
        self::assertSame($otherPolicy, $withPolicy->withPolicy($otherPolicy)->policy);
        self::assertSame($policy, $withPolicy->policy);
    }

    #[Test]
    public function it_denies_with_the_exact_denial(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');

        $result = AuthorisationResult::denied($denial);

        self::assertSame(AuthorisationStatus::Denied, $result->status);
        self::assertTrue($result->isDenied());
        self::assertSame($denial, $result->denial);
        self::assertNull($result->policy);
    }

    #[Test]
    public function it_keeps_the_status_and_the_denial_when_a_policy_is_attached(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');
        $result = AuthorisationResult::denied($denial);
        $policy = new FixedPolicy($result);

        $withPolicy = $result->withPolicy($policy);

        self::assertSame(AuthorisationStatus::Denied, $withPolicy->status);
        self::assertSame($policy, $withPolicy->policy);
        self::assertSame($denial, $withPolicy->denial);
        self::assertNull($result->policy);
        self::assertSame($denial, $result->denial);
    }

    #[Test]
    public function it_attaches_a_policy_without_adding_a_denial(): void
    {
        $result = AuthorisationResult::denied()->withPolicy(new FixedPolicy(AuthorisationResult::denied()));

        self::assertTrue($result->isDenied());
        self::assertNull($result->denial);
    }

    #[Test]
    public function it_has_consulted_no_policies_by_default(): void
    {
        self::assertSame([], AuthorisationResult::allowed()->consulted);
        self::assertSame([], AuthorisationResult::denied()->consulted);
        self::assertSame([], AuthorisationResult::notApplicable()->consulted);
    }

    #[Test]
    public function it_records_the_consulted_policies_as_a_new_result(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');
        $deciding = new FixedPolicy(AuthorisationResult::denied($denial));
        $other = new FixedPolicy(AuthorisationResult::notApplicable());
        $result = AuthorisationResult::denied($denial)->withPolicy($deciding);

        $consulted = $result->withConsulted($other, $deciding);

        self::assertNotSame($result, $consulted);
        self::assertSame([], $result->consulted);
        self::assertSame([$other, $deciding], $consulted->consulted);
        self::assertSame(AuthorisationStatus::Denied, $consulted->status);
        self::assertSame($deciding, $consulted->policy);
        self::assertSame($denial, $consulted->denial);
    }

    #[Test]
    public function it_records_the_consulted_policies_of_a_result_that_does_not_apply(): void
    {
        $policy = new FixedPolicy(AuthorisationResult::notApplicable());

        $result = AuthorisationResult::notApplicable()->withConsulted($policy);

        self::assertTrue($result->isNotApplicable());
        self::assertNull($result->policy);
        self::assertSame([$policy], $result->consulted);
    }

    #[Test]
    public function it_keeps_the_consulted_policies_when_a_policy_is_attached(): void
    {
        $policy = new FixedPolicy(AuthorisationResult::allowed());

        $result = AuthorisationResult::allowed()->withConsulted($policy)->withPolicy($policy);

        self::assertSame([$policy], $result->consulted);
    }

    #[Test]
    public function it_records_consulted_policies_as_a_list(): void
    {
        $policy = new FixedPolicy(AuthorisationResult::allowed());

        $result = AuthorisationResult::allowed()->withConsulted(...['first' => $policy]);

        self::assertSame([$policy], $result->consulted);
    }
}
