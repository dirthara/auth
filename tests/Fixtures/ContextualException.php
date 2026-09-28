<?php

declare(strict_types=1);

namespace Dirthara\Auth\Tests\Fixtures;

use RuntimeException;
use Dirthara\Auth\Exception\AuthException;
use Dirthara\Auth\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements AuthException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
