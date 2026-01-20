<?php
declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class ConstructorWithNullableBuiltin
{
    public function __construct(?string $value)
    {
    }
}