<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class ConstructorWithVariadic
{
    public function __construct(Foo ...$foos)
    {
    }
}