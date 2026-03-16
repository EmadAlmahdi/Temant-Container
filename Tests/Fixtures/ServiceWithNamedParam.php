<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class ServiceWithNamedParam
{
    public function __construct(
        public readonly Foo $foo,
        public readonly string $name,
        public readonly int $value = 42,
    ) {
    }
}
