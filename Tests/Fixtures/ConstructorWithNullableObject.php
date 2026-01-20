<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class ConstructorWithNullableObject
{
    public function __construct(?Foo $foo)
    {
    }
}