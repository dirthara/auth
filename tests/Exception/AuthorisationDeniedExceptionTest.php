<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Exception;

use stdClass;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\AuthorisationDenial;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationStatus;
use Dirthara\Authorisation\AuthorisationContext;
use Dirthara\Authorisation\Tests\Fixtures\Ability;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
use Dirthara\Authorisation\Exception\AuthorisationException;
use Dirthara\Authorisation\Exception\AuthorisationDeniedException;

final class AuthorisationDeniedExceptionTest extends TestCase
{
    #[Test]
    public function it_names_the_ability_and_the_policy_that_denied(): void
    {
        $policy = new FixedPolicy(AuthorisationResult::denied());
        $result = AuthorisationResult::denied()->withPolicy($policy);

        $exception = AuthorisationDeniedException::denied(new AuthorisationContext(new stdClass(), 'edit'), $result);

        self::assertInstanceOf(AuthorisationException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame($result, $exception->result);
        self::assertSame(
            'The authorisation of "edit" was denied by ' . FixedPolicy::class . '.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function it_leaves_out_the_policy_when_the_result_has_none(): void
    {
        $exception = AuthorisationDeniedException::denied(
            new AuthorisationContext(new stdClass(), 'edit'),
            AuthorisationResult::denied(),
        );

        self::assertSame('The authorisation of "edit" was denied.', $exception->getMessage());
        self::assertNull($exception->context['policy']);
    }

    #[Test]
    public function it_says_when_no_policy_applies(): void
    {
        $result = AuthorisationResult::notApplicable();

        $exception = AuthorisationDeniedException::noPolicyApplies(
            new AuthorisationContext(new stdClass(), Ability::Edit),
            $result,
        );

        self::assertSame($result, $exception->result);
        self::assertSame(
            'The authorisation of "' . Ability::class . '::Edit" was denied because no policy applies to it.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function it_escapes_control_characters_in_the_ability(): void
    {
        $exception = AuthorisationDeniedException::denied(
            new AuthorisationContext(new stdClass(), "edit\nforged"),
            AuthorisationResult::denied(),
        );

        self::assertSame('The authorisation of "edit\\nforged" was denied.', $exception->getMessage());
    }

    #[Test]
    public function it_records_the_denial_by_its_message_key_only(): void
    {
        $policy = new FixedPolicy(AuthorisationResult::denied());
        $result = AuthorisationResult::denied(new AuthorisationDenial('{actor} cannot modify this resource.', [
            'actor' => 'Ada',
        ]))->withPolicy($policy);

        $exception = AuthorisationDeniedException::denied(
            new AuthorisationContext(new stdClass(), 'edit', new LogicException()),
            $result,
        );

        self::assertSame(
            [
                'ability' => 'edit',
                'actorType' => stdClass::class,
                'subjectType' => LogicException::class,
                'status' => AuthorisationStatus::Denied,
                'policy' => FixedPolicy::class,
                'messageKey' => '{actor} cannot modify this resource.',
                'consulted' => [],
            ],
            $exception->context,
        );
    }

    #[Test]
    public function it_records_no_message_key_without_a_denial(): void
    {
        $exception = AuthorisationDeniedException::noPolicyApplies(
            new AuthorisationContext(new stdClass(), 'create'),
            AuthorisationResult::notApplicable(),
        );

        self::assertNull($exception->context['messageKey']);
        self::assertSame(AuthorisationStatus::NotApplicable, $exception->context['status']);
        self::assertSame('null', $exception->context['subjectType']);
    }

    #[Test]
    public function it_records_the_consulted_policies_by_type(): void
    {
        $result = AuthorisationResult::notApplicable()->withConsulted(
            new FixedPolicy(AuthorisationResult::notApplicable()),
            new FixedPolicy(AuthorisationResult::notApplicable()),
        );

        $exception = AuthorisationDeniedException::noPolicyApplies(
            new AuthorisationContext(new stdClass(), 'edit'),
            $result,
        );

        self::assertSame([FixedPolicy::class, FixedPolicy::class], $exception->context['consulted']);
    }
}
