<?php declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures {
    class Baz
    {
        public function __construct(private readonly Foo $foo, private readonly Bar $bar)
        {

        }
    }
}

