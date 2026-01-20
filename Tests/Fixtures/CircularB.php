<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}