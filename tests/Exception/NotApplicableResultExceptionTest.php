<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Exception;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Authorisation\AuthorisationResult;
use Dirthara\Authorisation\Tests\Fixtures\FixedPolicy;
use Dirthara\Authorisation\Exception\AuthorisationException;
use Dirthara\Authorisation\Exception\NotApplicableResultException;

final class NotApplicableResultExceptionTest extends TestCase
{
    #[Test]
    public function it_names_the_policy_that_was_attached(): void
    {
        $exception = NotApplicableResultException::cannotHavePolicy(
            new FixedPolicy(AuthorisationResult::notApplicable()),
        );

        self::assertInstanceOf(AuthorisationException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame(
            'A not applicable authorisation result cannot have a deciding policy, but '
            . FixedPolicy::class
            . ' was attached to one.',
            $exception->getMessage(),
        );
        self::assertSame(['policy' => FixedPolicy::class], $exception->context);
    }
}
