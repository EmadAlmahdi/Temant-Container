<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class UnionWithDefaultService
{
    public function __construct(public readonly Foo|Bar $dependency = new Bar())
    {
    }
}
