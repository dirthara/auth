<?php

declare(strict_types=1);

namespace Dirthara\Auth\Tests\Fixtures;

use Dirthara\Auth\Contract\Policy;
use Dirthara\Auth\AuthorisationResult;
use Dirthara\Auth\AuthorisationContext;

final class FixedPolicy implements Policy
{
    /**
     * @var list<AuthorisationContext>
     */
    public private(set) array $contexts = [];

    public function __construct(
        private readonly AuthorisationResult $result,
    ) {}

    public function authorise(AuthorisationContext $context): AuthorisationResult
    {
        $this->contexts[] = $context;

        return $this->result;
    }
}
