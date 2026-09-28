<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Exception;

use stdClass;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\Contract\Policy;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\AuthorisationContext;
use Dirthara\Authorisation\Tests\Fixtures\Ability;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
use Dirthara\Authorisation\Exception\AuthorisationException;
use Dirthara\Authorisation\Exception\AmbiguousPolicyException;

final class AmbiguousPolicyExceptionTest extends TestCase
{
    #[Test]
    public function it_names_the_ability_and_the_policies_that_apply(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), 'edit'));

        self::assertInstanceOf(AuthorisationException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame(
            'The authorisation of "edit" is ambiguous: both '
            . FixedPolicy::class
            . ' and '
            . FixedPolicy::class
            . ' apply to it.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function it_names_an_enum_ability_by_its_case(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), Ability::Edit));

        self::assertStringStartsWith(
            'The authorisation of "' . Ability::class . '::Edit" is ambiguous',
            $exception->getMessage(),
        );
        self::assertSame(Ability::class . '::Edit', $exception->context['ability']);
    }

    #[Test]
    public function it_escapes_control_characters_in_the_ability(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), "edit\nforged\r\tline"));

        self::assertStringStartsWith(
            'The authorisation of "edit\\nforged\\r\\tline" is ambiguous',
            $exception->getMessage(),
        );
        self::assertSame('edit\\nforged\\r\\tline', $exception->context['ability']);
    }

    #[Test]
    public function it_records_the_actor_and_the_subject_by_type_only(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), 'edit', new LogicException()));

        self::assertSame(
            [
                'ability' => 'edit',
                'actorType' => stdClass::class,
                'subjectType' => LogicException::class,
                'policies' => [FixedPolicy::class, FixedPolicy::class],
            ],
            $exception->context,
        );
    }

    #[Test]
    public function it_records_a_missing_subject_as_null(): void
    {
        $exception = self::exceptionFor(new AuthorisationContext(new stdClass(), 'create'));

        self::assertSame('null', $exception->context['subjectType']);
    }

    #[Test]
    public function it_names_anonymous_policies_without_their_file(): void
    {
        $exception = AmbiguousPolicyException::forContext(
            new AuthorisationContext(new stdClass(), 'edit'),
            self::anonymousPolicy(),
            self::anonymousPolicy(),
        );

        self::assertSame(
            'The authorisation of "edit" is ambiguous: both '
            . Policy::class
            . '@anonymous and '
            . Policy::class
            . '@anonymous apply to it.',
            $exception->getMessage(),
        );
        self::assertSame([Policy::class . '@anonymous', Policy::class . '@anonymous'], $exception->context['policies']);
    }

    private static function exceptionFor(AuthorisationContext $context): AmbiguousPolicyException
    {
        return AmbiguousPolicyException::forContext(
            $context,
            new FixedPolicy(AuthorisationResult::allowed()),
            new FixedPolicy(AuthorisationResult::denied()),
        );
    }

    private static function anonymousPolicy(): Policy
    {
        return new class implements Policy {
            public function authorise(AuthorisationContext $context): AuthorisationResult
            {
                return AuthorisationResult::allowed();
            }
        };
    }
}
