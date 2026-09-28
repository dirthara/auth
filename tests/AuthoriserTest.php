<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests;

use stdClass;
use Generator;
use PHPUnit\Framework\TestCase;
use Dirthara\Authorisation\Authoriser;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\DecisionStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
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
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::allowed()),
            new FixedPolicy(AuthorisationResult::allowed()),
        ]);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    public function it_refuses_to_decide_with_only_one_policy_when_more_than_one_applies(): void
    {
        $authoriser = new Authoriser([
            new FixedPolicy(AuthorisationResult::allowed()),
            new FixedPolicy(AuthorisationResult::denied()),
        ], DecisionStrategy::OnlyOne);

        $this->expectException(AmbiguousPolicyException::class);

        $authoriser->authorise(self::context());
    }

    #[Test]
    public function it_allows_when_at_least_one_policy_allows(): void
    {
        $denying = new FixedPolicy(AuthorisationResult::denied());
        $allowing = new FixedPolicy(AuthorisationResult::allowed());
        $alsoAllowing = new FixedPolicy(AuthorisationResult::allowed());

        $result = new Authoriser([$denying, $allowing, $alsoAllowing], DecisionStrategy::AtLeastOne)->authorise(
            self::context(),
        );

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
        self::assertNull($result->denial);
    }

    #[Test]
    public function it_denies_with_the_first_policy_that_denies_when_no_policy_allows_at_least_once(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');
        $denying = new FixedPolicy(AuthorisationResult::denied($denial));
        $alsoDenying = new FixedPolicy(AuthorisationResult::denied());

        $result = new Authoriser([
            new FixedPolicy(AuthorisationResult::notApplicable()),
            $denying,
            $alsoDenying,
        ], DecisionStrategy::AtLeastOne)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
        self::assertSame($denial, $result->denial);
    }

    #[Test]
    public function it_denies_when_one_of_all_policies_denies(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');
        $allowing = new FixedPolicy(AuthorisationResult::allowed());
        $denying = new FixedPolicy(AuthorisationResult::denied($denial));
        $alsoDenying = new FixedPolicy(AuthorisationResult::denied());

        $result = new Authoriser([$allowing, $denying, $alsoDenying], DecisionStrategy::All)->authorise(
            self::context(),
        );

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
        self::assertSame($denial, $result->denial);
    }

    #[Test]
    public function it_allows_with_the_first_policy_when_all_policies_that_apply_allow(): void
    {
        $allowing = new FixedPolicy(AuthorisationResult::allowed());
        $alsoAllowing = new FixedPolicy(AuthorisationResult::allowed());

        $result = new Authoriser([
            new FixedPolicy(AuthorisationResult::notApplicable()),
            $allowing,
            $alsoAllowing,
        ], DecisionStrategy::All)->authorise(self::context());

        self::assertTrue($result->isAllowed());
        self::assertSame($allowing, $result->policy);
    }

    #[Test]
    #[DataProvider('strategies')]
    public function it_does_not_apply_when_no_policy_applies_with_any_strategy(DecisionStrategy $strategy): void
    {
        $result = new Authoriser([
            new FixedPolicy(AuthorisationResult::notApplicable()),
            new FixedPolicy(AuthorisationResult::notApplicable()),
        ], $strategy)->authorise(self::context());

        self::assertTrue($result->isNotApplicable());
        self::assertNull($result->policy);
    }

    #[Test]
    #[DataProvider('strategies')]
    public function it_decides_with_the_one_policy_that_applies_with_any_strategy(DecisionStrategy $strategy): void
    {
        $denying = new FixedPolicy(AuthorisationResult::denied());

        $result = new Authoriser([
            new FixedPolicy(AuthorisationResult::notApplicable()),
            $denying,
        ], $strategy)->authorise(self::context());

        self::assertTrue($result->isDenied());
        self::assertSame($denying, $result->policy);
    }

    #[Test]
    #[DataProvider('agreeingStrategies')]
    public function it_asks_every_policy_after_the_outcome_is_certain(DecisionStrategy $strategy): void
    {
        $first = new FixedPolicy(AuthorisationResult::denied());
        $second = new FixedPolicy(AuthorisationResult::allowed());
        $third = new FixedPolicy(AuthorisationResult::denied());

        $result = new Authoriser([$first, $second, $third], $strategy)->authorise(self::context());

        self::assertCount(1, $third->contexts);
        self::assertSame([$first, $second, $third], $result->consulted);
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
