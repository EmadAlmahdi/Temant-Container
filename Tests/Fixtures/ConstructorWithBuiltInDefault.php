<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class ConstructorWithBuiltInDefault
{
    public function __construct(public string $name = 'hello')
    {
    }
}