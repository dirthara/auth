<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests;

use stdClass;
use Generator;
use PHPUnit\Framework\TestCase;
use Dirthara\Authorisation\Authoriser;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\PolicyDecision;
use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\DecisionStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
use Dirthara\Authorisation\Exception\InvalidPolicyException;
use Dirthara\Authorisation\Exception\AmbiguousPolicyException;
use Dirthara\Authorisation\Contract\Authoriser as AuthoriserContract;

final class AuthoriserTest extends TestCase
{
    #[Test]
    public function it_implements_the_authoriser_contract(): void
    {
        self::assertInstanceOf(AuthoriserContract::class, new Authoriser([]));
    }

    #[Test]
    public function it_does_not_apply_without_policies(): void
    {
        $result = new Authoriser([])->authorise(self::context());

        self::assertTrue($result->isNotApplicable());
        self::assertNull($result->policy);
        self::assertNull($result->denial);
    }

    #[Test]
    public function it_does_not_apply_when_no_policy_applies(): void
    {
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::notApplicable()),
            new FixedPolicy(AuthorisationResult::notApplicable()),
        ]);

        $result = $authoriser->authorise(self::context());

        self::assertTrue($result->isNotApplicable());
        self::assertNull($result->policy);
        self::assertNull($result->denial);
    }

    #[Test]
    public function it_attaches_the_one_policy_that_allows(): void
    {
        $allowing = new FixedPolicy(AuthorisationResult::allowed());

        $result = new Authoriser([$allowing])->authorise(self::context());

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
        self::assertNull($result->denial);
    }

    #[Test]
    public function it_attaches_the_one_policy_that_denies(): void
    {
        $denying = new FixedPolicy(AuthorisationResult::denied());

        $result = new Authoriser([$denying])->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
        self::assertNull($result->denial);
    }

    #[Test]
    public function it_keeps_the_denial_of_the_one_policy_that_denies(): void
    {
        $denial = new AuthorisationDenial('{actor} cannot modify this resource.', parameters: ['actor' => 'Ada']);
        $denying = new FixedPolicy(AuthorisationResult::denied($denial));
        $authoriser = new Authoriser([new FixedPolicy(AuthorisationResult::notApplicable()), $denying]);

        $result = $authoriser->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
        self::assertSame($denial, $result->denial);
        self::assertSame('Ada cannot modify this resource.', $denial->message);
    }

    #[Test]
    public function it_refuses_to_decide_when_a_policy_that_denies_with_a_denial_is_not_the_only_one(): void
    {
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::denied(new AuthorisationDenial('You can only edit posts you own.'))),
            new FixedPolicy(AuthorisationResult::allowed()),
        ]);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    public function it_attaches_the_applicable_policy_between_policies_that_do_not_apply(): void
    {
        $before = new FixedPolicy(AuthorisationResult::notApplicable());
        $allowing = new FixedPolicy(AuthorisationResult::allowed());
        $after = new FixedPolicy(AuthorisationResult::notApplicable());

        $result = new Authoriser([$before, $allowing, $after])->authorise(self::context());

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
        self::assertNotSame($before, $result->policy);
        self::assertNotSame($after, $result->policy);
    }

    #[Test]
    public function it_does_not_attach_the_policy_to_the_result_the_policy_returns(): void
    {
        $allowed = AuthorisationResult::allowed();
        $policy = new FixedPolicy($allowed);
        $context = self::context();

        self::assertNull($policy->authorise($context)->policy);

        $result = new Authoriser([$policy])->authorise($context);

        self::assertSame($policy, $result->policy);
        self::assertNull($allowed->policy);
        self::assertNull($policy->authorise($context)->policy);
    }

    #[Test]
    public function it_asks_every_policy_about_the_same_context(): void
    {
        $first = new FixedPolicy(AuthorisationResult::allowed());
        $second = new FixedPolicy(AuthorisationResult::notApplicable());
        $context = self::context();

        new Authoriser([$first, $second])->authorise($context);

        self::assertSame([$context], $first->contexts);
        self::assertSame([$context], $second->contexts);
    }

    #[Test]
    public function it_refuses_to_decide_when_more_than_one_policy_applies(): void
    {
        $allowing = new FixedPolicy(AuthorisationResult::allowed());
        $denying = new FixedPolicy(AuthorisationResult::denied());
        $authoriser = new Authoriser([$allowing, new FixedPolicy(AuthorisationResult::notApplicable()), $denying]);

        try {
            $authoriser->authorise(self::context());
            self::fail('Expected an AmbiguousPolicyException.');
        } catch (AmbiguousPolicyException $exception) {
            self::assertSame([FixedPolicy::class, FixedPolicy::class], $exception->context['policies']);
        }
    }

    #[Test]
    public function it_refuses_to_decide_when_policies_that_allow_agree(): void
    {
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::allowed()),
            new FixedPolicy(AuthorisationResult::allowed()),
        ]);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    public function it_refuses_to_decide_when_policies_that_deny_agree(): void
    {
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::denied()),
            new FixedPolicy(AuthorisationResult::denied()),
        ]);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    public function it_takes_the_policies_from_a_generator(): void
    {
        $allowing = new FixedPolicy(AuthorisationResult::allowed());
        $policies = self::generate(new FixedPolicy(AuthorisationResult::notApplicable()), $allowing);

        $result = new Authoriser($policies)->authorise(self::context());

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
    }

    #[Test]
    public function it_keeps_every_policy_when_a_generator_repeats_a_key(): void
    {
        $policies = self::generateUnderOneKey(
            new FixedPolicy(AuthorisationResult::denied()),
            new FixedPolicy(AuthorisationResult::allowed()),
        );

        $this->expectException(AmbiguousPolicyException::class);

        new Authoriser($policies)->authorise(self::context());
    }

    #[Test]
    public function it_records_every_policy_it_consulted_when_none_applies(): void
    {
        $first = new FixedPolicy(AuthorisationResult::notApplicable());
        $second = new FixedPolicy(AuthorisationResult::notApplicable());

        $result = new Authoriser([$first, $second])->authorise(self::context());

        self::assertSame([$first, $second], $result->consulted);
    }

    #[Test]
    public function it_records_every_policy_it_consulted_when_one_decides(): void
    {
        $before = new FixedPolicy(AuthorisationResult::notApplicable());
        $deciding = new FixedPolicy(AuthorisationResult::allowed());
        $after = new FixedPolicy(AuthorisationResult::notApplicable());

        $result = new Authoriser([$before, $deciding, $after])->authorise(self::context());

        self::assertSame([$before, $deciding, $after], $result->consulted);
        self::assertSame($deciding, $result->policy);
    }

    #[Test]
    public function it_records_no_consulted_policies_without_policies(): void
    {
        self::assertSame([], new Authoriser([])->authorise(self::context())->consulted);
    }

    #[Test]
    public function it_decides_with_only_one_policy_by_default(): void
    {
        $authoriser = new Authoriser([self::allowing(), self::allowing()]);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    #[DataProvider('ambiguousPairs')]
    public function it_refuses_to_decide_with_only_one_policy_when_two_apply(Policy $first, Policy $second): void
    {
        $authoriser = new Authoriser([$first, $second], DecisionStrategy::OnlyOne);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    public function it_attaches_the_one_policy_that_allows_with_only_one_policy(): void
    {
        $allowing = self::allowing();

        $result = new Authoriser([self::abstaining(), $allowing], DecisionStrategy::OnlyOne)->authorise(
            self::context(),
        );

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
        self::assertNull($result->denial);
        self::assertEquals([PolicyDecision::allowed($allowing)], $result->decisions);
    }

    #[Test]
    public function it_attaches_the_one_policy_that_denies_and_its_denial_with_only_one_policy(): void
    {
        $denial = new AuthorisationDenial('You do not own this resource.');
        $denying = self::denying($denial);

        $result = new Authoriser([$denying, self::abstaining()], DecisionStrategy::OnlyOne)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
        self::assertSame($denial, $result->denial);
        self::assertEquals([PolicyDecision::denied($denying, $denial)], $result->decisions);
    }

    #[Test]
    public function it_allows_with_the_one_policy_that_allows_at_least_once(): void
    {
        $allowing = self::allowing();

        $result = new Authoriser([$allowing], DecisionStrategy::AtLeastOne)->authorise(self::context());

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
        self::assertEquals([PolicyDecision::allowed($allowing)], $result->decisions);
    }

    #[Test]
    public function it_denies_collectively_when_the_one_policy_that_applies_denies_at_least_once(): void
    {
        $denial = new AuthorisationDenial('You do not own this resource.');
        $denying = self::denying($denial);

        $result = new Authoriser([$denying], DecisionStrategy::AtLeastOne)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertNull($result->policy);
        self::assertNull($result->denial);
        self::assertEquals([PolicyDecision::denied($denying, $denial)], $result->decisions);
    }

    #[Test]
    public function it_allows_with_the_first_policy_that_allows_after_a_denial_at_least_once(): void
    {
        $denial = new AuthorisationDenial('You do not own this resource.');
        $denying = self::denying($denial);
        $allowing = self::allowing();
        $alsoAllowing = self::allowing();

        $result = new Authoriser([$denying, $allowing, $alsoAllowing], DecisionStrategy::AtLeastOne)->authorise(
            self::context(),
        );

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
        self::assertNull($result->denial);
        self::assertEquals(
            [
                PolicyDecision::denied($denying, $denial),
                PolicyDecision::allowed($allowing),
                PolicyDecision::allowed($alsoAllowing),
            ],
            $result->decisions,
        );
    }

    #[Test]
    public function it_allows_with_the_policy_that_allows_before_a_denial_at_least_once(): void
    {
        $allowing = self::allowing();
        $denying = self::denying();

        $result = new Authoriser([$allowing, $denying], DecisionStrategy::AtLeastOne)->authorise(self::context());

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
        self::assertEquals([PolicyDecision::allowed($allowing), PolicyDecision::denied($denying)], $result->decisions);
    }

    #[Test]
    public function it_denies_collectively_and_keeps_every_denial_when_every_policy_denies_at_least_once(): void
    {
        $notOwner = new AuthorisationDenial('You do not own this resource.');
        $noSubscription = new AuthorisationDenial('Your subscription does not permit this action.');
        $first = self::denying($notOwner);
        $second = self::denying($noSubscription);
        $third = self::denying();

        $result = new Authoriser([
            $first,
            self::abstaining(),
            $second,
            $third,
        ], DecisionStrategy::AtLeastOne)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertNull($result->policy);
        self::assertNull($result->denial);
        self::assertEquals(
            [
                PolicyDecision::denied($first, $notOwner),
                PolicyDecision::denied($second, $noSubscription),
                PolicyDecision::denied($third),
            ],
            $result->decisions,
        );
        self::assertSame($notOwner, $result->decisions[0]->denial);
        self::assertSame($noSubscription, $result->decisions[1]->denial);
    }

    #[Test]
    public function it_allows_collectively_when_the_one_policy_that_applies_allows_for_all(): void
    {
        $allowing = self::allowing();

        $result = new Authoriser([$allowing], DecisionStrategy::All)->authorise(self::context());

        self::assertTrue($result->isAllowed());
        self::assertNull($result->policy);
        self::assertEquals([PolicyDecision::allowed($allowing)], $result->decisions);
    }

    #[Test]
    public function it_denies_with_the_one_policy_that_denies_for_all(): void
    {
        $denial = new AuthorisationDenial('You do not own this resource.');
        $denying = self::denying($denial);

        $result = new Authoriser([$denying], DecisionStrategy::All)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
        self::assertSame($denial, $result->denial);
        self::assertEquals([PolicyDecision::denied($denying, $denial)], $result->decisions);
    }

    #[Test]
    public function it_allows_collectively_when_every_policy_that_applies_allows_for_all(): void
    {
        $first = self::allowing();
        $second = self::allowing();

        $result = new Authoriser([$first, self::abstaining(), $second], DecisionStrategy::All)->authorise(
            self::context(),
        );

        self::assertTrue($result->isAllowed());
        self::assertNull($result->policy);
        self::assertNull($result->denial);
        self::assertEquals([PolicyDecision::allowed($first), PolicyDecision::allowed($second)], $result->decisions);
    }

    #[Test]
    public function it_denies_with_the_policy_that_denies_after_an_allow_for_all(): void
    {
        $denial = new AuthorisationDenial('Your subscription does not permit this action.');
        $allowing = self::allowing();
        $denying = self::denying($denial);

        $result = new Authoriser([$allowing, $denying], DecisionStrategy::All)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
        self::assertSame($denial, $result->denial);
        self::assertEquals(
            [PolicyDecision::allowed($allowing), PolicyDecision::denied($denying, $denial)],
            $result->decisions,
        );
    }

    #[Test]
    public function it_denies_with_the_first_policy_that_denies_and_keeps_every_denial_for_all(): void
    {
        $notOwner = new AuthorisationDenial('You do not own this resource.');
        $noSubscription = new AuthorisationDenial('Your subscription does not permit this action.');
        $first = self::denying($notOwner);
        $allowing = self::allowing();
        $second = self::denying($noSubscription);

        $result = new Authoriser([$first, $allowing, $second], DecisionStrategy::All)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($first, $result->policy);
        self::assertSame($notOwner, $result->denial);
        self::assertEquals(
            [
                PolicyDecision::denied($first, $notOwner),
                PolicyDecision::allowed($allowing),
                PolicyDecision::denied($second, $noSubscription),
            ],
            $result->decisions,
        );
    }

    #[Test]
    #[DataProvider('strategies')]
    public function it_does_not_apply_without_a_policy_that_applies_with_any_strategy(DecisionStrategy $strategy): void
    {
        $first = self::abstaining();
        $second = self::abstaining();

        $result = new Authoriser([$first, $second], $strategy)->authorise(self::context());

        self::assertTrue($result->isNotApplicable());
        self::assertNull($result->policy);
        self::assertNull($result->denial);
        self::assertSame([], $result->decisions);
        self::assertSame([$first, $second], $result->consulted);
    }

    #[Test]
    #[DataProvider('strategies')]
    public function it_does_not_apply_without_policies_with_any_strategy(DecisionStrategy $strategy): void
    {
        $result = new Authoriser([], $strategy)->authorise(self::context());

        self::assertTrue($result->isNotApplicable());
        self::assertSame([], $result->decisions);
        self::assertSame([], $result->consulted);
    }

    #[Test]
    #[DataProvider('agreeingStrategies')]
    public function it_asks_every_policy_after_the_outcome_is_certain(DecisionStrategy $strategy): void
    {
        $first = self::denying();
        $second = self::allowing();
        $third = self::denying();

        $result = new Authoriser([$first, $second, $third], $strategy)->authorise(self::context());

        self::assertCount(1, $third->contexts);
        self::assertSame([$first, $second, $third], $result->consulted);
        self::assertCount(3, $result->decisions);
    }

    #[Test]
    #[DataProvider('strategies')]
    public function it_records_decisions_in_the_order_of_the_policies(DecisionStrategy $strategy): void
    {
        $denying = self::denying();

        $result = new Authoriser(self::generate(self::abstaining(), $denying), $strategy)->authorise(self::context());

        self::assertEquals([PolicyDecision::denied($denying)], $result->decisions);
    }

    #[Test]
    public function it_replaces_what_a_policy_attached_to_its_own_result(): void
    {
        $inner = self::allowing();
        $deciding = new FixedPolicy(
            AuthorisationResult::allowed()
                ->withPolicy($inner)
                ->withConsulted($inner)
                ->withDecisions(PolicyDecision::allowed($inner)),
        );

        $result = new Authoriser([$deciding])->authorise(self::context());

        self::assertSame($deciding, $result->policy);
        self::assertSame([$deciding], $result->consulted);
        self::assertEquals([PolicyDecision::allowed($deciding)], $result->decisions);
    }

    #[Test]
    public function it_takes_an_array_of_policies(): void
    {
        $first = self::abstaining();
        $second = self::allowing();

        $result = new Authoriser([$first, $second])->authorise(self::context());

        self::assertSame([$first, $second], $result->consulted);
    }

    #[Test]
    #[DataProvider('invalidPolicies')]
    public function it_rejects_a_value_that_is_not_a_policy(mixed $value, string $type): void
    {
        try {
            new Authoriser([self::allowing(), $value, self::allowing()]);
            self::fail('The value was accepted as a policy.');
        } catch (InvalidPolicyException $exception) {
            self::assertSame(['position' => 1, 'type' => $type], $exception->context);
        }
    }

    #[Test]
    public function it_rejects_a_value_that_is_not_a_policy_from_a_generator(): void
    {
        $generator = (static function (): Generator {
            yield 'first' => new FixedPolicy(AuthorisationResult::allowed());
            yield 'second' => new FixedPolicy(AuthorisationResult::denied());
            yield 'third' => 'edit';
        })();

        try {
            new Authoriser($generator);
            self::fail('The value was accepted as a policy.');
        } catch (InvalidPolicyException $exception) {
            self::assertSame(['position' => 2, 'type' => 'string'], $exception->context);
        }
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidPolicies(): iterable
    {
        yield 'string' => ['ArticlePolicy', 'string'];
        yield 'integer' => [1, 'int'];
        yield 'null' => [null, 'null'];
        yield 'array' => [[], 'array'];
        yield 'object' => [new stdClass(), stdClass::class];
        yield 'closure' => [static fn(): bool => true, 'Closure'];
    }

    /**
     * @return iterable<string, array{Policy, Policy}>
     */
    public static function ambiguousPairs(): iterable
    {
        yield 'two that allow' => [self::allowing(), self::allowing()];
        yield 'two that deny' => [self::denying(), self::denying()];
        yield 'one that allows and one that denies' => [self::allowing(), self::denying()];
    }

    /**
     * @return iterable<string, array{DecisionStrategy}>
     */
    public static function strategies(): iterable
    {
        foreach (DecisionStrategy::cases() as $strategy) {
            yield $strategy->name => [$strategy];
        }
    }

    /**
     * @return iterable<string, array{DecisionStrategy}>
     */
    public static function agreeingStrategies(): iterable
    {
        yield 'at least one' => [DecisionStrategy::AtLeastOne];
        yield 'all' => [DecisionStrategy::All];
    }

    private static function allowing(): FixedPolicy
    {
        return new FixedPolicy(AuthorisationResult::allowed());
    }

    private static function denying(?AuthorisationDenial $denial = null): FixedPolicy
    {
        return new FixedPolicy(AuthorisationResult::denied($denial));
    }

    private static function abstaining(): FixedPolicy
    {
        return new FixedPolicy(AuthorisationResult::notApplicable());
    }

    private static function context(): AuthorisationContext
    {
        return new AuthorisationContext(new stdClass(), 'edit', new stdClass());
    }

    /**
     * @return Generator<int, Policy>
     */
    private static function generate(Policy ...$policies): Generator
    {
        foreach ($policies as $policy) {
            yield $policy;
        }
    }

    /**
     * @return Generator<string, Policy>
     */
    private static function generateUnderOneKey(Policy ...$policies): Generator
    {
        foreach ($policies as $policy) {
            yield 'policy' => $policy;
        }
    }
}
