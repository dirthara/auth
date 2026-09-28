<?php

declare(strict_types=1);

namespace Dirthara\Authorisation\Exception;

use UnitEnum;

use function addcslashes;
use function array_merge;

trait HasExceptionContext
{
    /**
     * @var array<string, mixed>
     */
    public protected(set) array $context = [] {
        get => $this->context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Escapes control characters in a value quoted in a message, so a rejected value cannot forge a line in a log.
     */
    private static function printable(string $value): string
    {
        return addcslashes($value, characters: "\0..\37\177");
    }

    private static function printableAbility(string|UnitEnum $ability): string
    {
        return $ability instanceof UnitEnum ? $ability::class . '::' . $ability->name : self::printable($ability);
    }
}
