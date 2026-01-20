<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class DefaultObjectDep
{
    public function __construct()
    {
    }
}

final class ConstructorWithDefaultObject
{
    public function __construct(public DefaultObjectDep $dep = new DefaultObjectDep())
    {
    }
}