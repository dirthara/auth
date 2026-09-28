<?php

declare(strict_types=1);

namespace Dirthara\Authorisation;

enum DecisionStrategy
{
    case OnlyOne;
    case AtLeastOne;
    case All;
}
