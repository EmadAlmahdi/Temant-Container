<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

interface A
{
}
interface B
{
}

final class ConstructorWithIntersectionType
{
    public function __construct(A&B $value)
    {
    }
}