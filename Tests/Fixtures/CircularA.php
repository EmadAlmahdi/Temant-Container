<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}