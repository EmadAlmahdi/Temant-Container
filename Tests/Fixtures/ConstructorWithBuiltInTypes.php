<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

class ConstructorWithBuiltInTypes
{
    public function __construct(private int $param)
    {
    }
}