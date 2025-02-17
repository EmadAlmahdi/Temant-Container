<?php declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures {
    class ConstructorWithDefaultValues
    {
        public function __construct(private Foo $param = new Foo)
        {
        }
    }
}