<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class CallTarget
{
    public function method(SomeClass $obj, string $name): string
    {
        return $obj::class . ':' . $name;
    }
}