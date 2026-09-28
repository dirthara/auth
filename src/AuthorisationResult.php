<?php

declare(strict_types=1);

namespace Dirthara\Auth;

final readonly class AuthorisationResult
{
    private function __construct(
        public AuthorisationStatus $status,
    ) {}

    public static function allowed(): self
    {
        return new self(AuthorisationStatus::Allowed);
    }

    public static function denied(): self
    {
        return new self(AuthorisationStatus::Denied);
    }

    public static function notApplicable(): self
    {
        return new self(AuthorisationStatus::NotApplicable);
    }

    public function isAllowed(): bool
    {
        return $this->status === AuthorisationStatus::Allowed;
    }

    public function isDenied(): bool
    {
        return $this->status === AuthorisationStatus::Denied;
    }

    public function isNotApplicable(): bool
    {
        return $this->status === AuthorisationStatus::NotApplicable;
    }
}
