<?php

declare(strict_types=1);

namespace Dirthara\Auth\Exception;

use Throwable;

interface AuthException extends Throwable
{
    /**
     * @var array<string, mixed>
     */
    public array $context { get; }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static;
}
