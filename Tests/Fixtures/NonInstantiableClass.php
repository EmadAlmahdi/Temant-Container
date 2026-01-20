<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

abstract class NonInstantiableClass
{
    private function __construct()
    {
    }
}