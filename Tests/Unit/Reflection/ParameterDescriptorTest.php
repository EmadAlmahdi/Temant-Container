<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Reflection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionParameter;
use Temant\Container\Reflection\ParameterDescriptor;
use Temant\Container\Reflection\ParameterTypeKind;
use Tests\Temant\Container\Fixtures\ConstructorWithDefaultObject;
use Tests\Temant\Container\Fixtures\ConstructorWithIntersectionType;
use Tests\Temant\Container\Fixtures\ConstructorWithNullableObject;
use Tests\Temant\Container\Fixtures\ConstructorWithTypedVariadic;
use Tests\Temant\Container\Fixtures\DefaultObjectDep;
use Tests\Temant\Container\Fixtures\InjectService;
use Tests\Temant\Container\Fixtures\ServiceWithNamedParam;
use Tests\Temant\Container\Fixtures\UnionService;

final class ParameterDescriptorTest extends TestCase
{
    private static function firstParam(string $class): ReflectionParameter
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        return $constructor->getParameters()[0];
    }

    #[Test]
    public function capturesNamedTypeFacts(): void
    {
        $d = ParameterDescriptor::fromReflection(self::firstParam(ServiceWithNamedParam::class));

        self::assertSame('foo', $d->name);
        self::assertSame(ParameterTypeKind::Named, $d->typeKind);
        self::assertSame(\Tests\Temant\Container\Fixtures\Foo::class, $d->typeName);
        self::assertFalse($d->isBuiltin);
        self::assertFalse($d->isVariadic);
        self::assertFalse($d->hasDefault);
        self::assertNull($d->injectId);
    }

    #[Test]
    public function capturesNullableAndDefault(): void
    {
        $nullable = ParameterDescriptor::fromReflection(self::firstParam(ConstructorWithNullableObject::class));
        self::assertTrue($nullable->allowsNull);

        $constructor = (new ReflectionClass(ServiceWithNamedParam::class))->getConstructor();
        self::assertNotNull($constructor);
        $withDefault = ParameterDescriptor::fromReflection($constructor->getParameters()[2]);
        self::assertTrue($withDefault->hasDefault);
        self::assertFalse($withDefault->defaultIsComplex);
        self::assertSame(42, $withDefault->default());
    }

    #[Test]
    public function capturesUnionMembers(): void
    {
        $d = ParameterDescriptor::fromReflection(self::firstParam(UnionService::class));

        self::assertSame(ParameterTypeKind::Union, $d->typeKind);
        self::assertEqualsCanonicalizing(
            [\Tests\Temant\Container\Fixtures\Foo::class, \Tests\Temant\Container\Fixtures\Bar::class],
            $d->unionTypeNames,
        );
    }

    #[Test]
    public function capturesIntersectionAndVariadic(): void
    {
        self::assertSame(
            ParameterTypeKind::Intersection,
            ParameterDescriptor::fromReflection(self::firstParam(ConstructorWithIntersectionType::class))->typeKind,
        );

        self::assertTrue(
            ParameterDescriptor::fromReflection(self::firstParam(ConstructorWithTypedVariadic::class))->isVariadic,
        );
    }

    #[Test]
    public function capturesInjectAttribute(): void
    {
        $d = ParameterDescriptor::fromReflection(self::firstParam(InjectService::class));

        self::assertSame(\Tests\Temant\Container\Fixtures\ConsoleLogger::class, $d->injectId);
    }

    #[Test]
    public function objectDefaultIsComplexAndFreshEachCall(): void
    {
        $d = ParameterDescriptor::fromReflection(self::firstParam(ConstructorWithDefaultObject::class));

        self::assertTrue($d->hasDefault);
        self::assertTrue($d->defaultIsComplex);
        self::assertInstanceOf(DefaultObjectDep::class, $d->default());
        self::assertNotSame($d->default(), $d->default(), 'each call rebuilds the object default');
    }

    #[Test]
    public function serializesWithoutTheDefaultProvider(): void
    {
        $original = ParameterDescriptor::fromReflection(self::firstParam(UnionService::class));

        /** @var ParameterDescriptor $restored */
        $restored = unserialize(serialize($original));

        self::assertSame($original->name, $restored->name);
        self::assertSame($original->typeKind, $restored->typeKind);
        self::assertSame($original->unionTypeNames, $restored->unionTypeNames);
    }
}
