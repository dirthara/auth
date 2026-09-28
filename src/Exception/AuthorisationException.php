<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Exception;

use Throwable;

interface AuthorisationException extends Throwable
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
