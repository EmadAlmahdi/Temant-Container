<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class UnionService
{
    public function __construct(public readonly Foo|Bar $dependency)
    {
    }
}
