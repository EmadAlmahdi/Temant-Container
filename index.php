<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Temant\Container\Container;

require __DIR__ . "/vendor/autoload.php";

$container = new Container(true);

final class Foo
{
    public function __construct(private readonly string $name = "Emad")
    {

    }

    public function getName(): string
    {
        return $this->name;
    }
}

$instance = new Foo();
$container->instance(Foo::class,  $instance);
$container->instance(Foo::class,  $instance); // throws ContainerException
dd($container); // false

final class Bar
{
    public function getName(): string
    {
        return "Hello";
    }
}

interface ComplexInterface
{
    public function getName(): string;
}

final class Complex implements ComplexInterface
{
    public function __construct(private readonly Foo $foo, private readonly Bar $bar)
    {
    }

    public function getName(): string
    {
        return sprintf("Hello from complex. \nFoo has name %s", $this->foo->getName());
    }
}

$container->multi([
    ComplexInterface::class => function (ContainerInterface $c) {
        return new Complex($c->get(Foo::class), $c->get(Bar::class));
    }
]);

class Zoo
{
    public function __construct(private readonly Foo $foo, private readonly Bar $bar)
    {
    }

    public function getName(): string
    {
        return sprintf("Hello from ZOO. \nFoo has name %s", $this->foo->getName());
    }
}

$complex = $container->get(complex::class);

dd($complex->getName());