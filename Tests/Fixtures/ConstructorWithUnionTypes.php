<?php declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures {
    class ConstructorWithUnionTypes
    {
        public function __construct(private Foo|Bar $param)
        {
        }
    }
}