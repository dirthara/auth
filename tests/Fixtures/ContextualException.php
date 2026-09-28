<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Tests\Fixtures;

use RuntimeException;
use Dirthara\Authorisation\Exception\AuthException;
use Dirthara\Authorisation\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements AuthException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
