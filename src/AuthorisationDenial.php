<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

use Stringable;

use function strtr;
use function implode;
use function is_bool;
use function is_array;
use function array_map;
use function is_scalar;
use function array_keys;
use function get_debug_type;

final class AuthorisationDenial
{
    public string $message {
        get {
            $replacements = [];

            foreach (array_keys($this->parameters) as $name) {
                $replacements['{' . $name . '}'] = self::render($this->parameters[$name]);
            }

            return strtr($this->messageKey, $replacements);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly string $messageKey,
        public readonly array $parameters = [],
    ) {}

    private static function render(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value), $value instanceof Stringable => (string) $value,
            is_array($value) => implode(', ', array_map(self::render(...), $value)),
            default => get_debug_type($value),
        };
    }
}
