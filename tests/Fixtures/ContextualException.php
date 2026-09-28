<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Fixtures;

use RuntimeException;
use Dirthara\Authorisation\Exception\HasExceptionContext;
use Dirthara\Authorisation\Exception\AuthorisationException;

final class ContextualException extends RuntimeException implements AuthorisationException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
