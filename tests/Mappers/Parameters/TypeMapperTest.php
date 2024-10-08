<?php

declare(strict_types=1);

namespace TheCodingMachine\GraphQLite\Mappers\Parameters;

use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\UnionType;
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use TheCodingMachine\GraphQLite\AbstractQueryProvider;
use TheCodingMachine\GraphQLite\Annotations\HideParameter;
use TheCodingMachine\GraphQLite\Fixtures\UnionOutputType;
use TheCodingMachine\GraphQLite\Mappers\CannotMapTypeException;
use TheCodingMachine\GraphQLite\Parameters\DefaultValueParameter;
use TheCodingMachine\GraphQLite\Parameters\InputTypeParameter;
use TheCodingMachine\GraphQLite\Reflection\DocBlock\CachedDocBlockFactory;

use function assert;
use function count;

class TypeMapperTest extends AbstractQueryProvider
{
    public function testMapScalarUnionException(): void
    {
        $docBlockFactory = $this->getDocBlockFactory();

        $typeMapper = new TypeHandler(
            $this->getArgumentResolver(),
            $this->getRootTypeMapper(),
            $this->getTypeResolver(),
            $docBlockFactory,
        );

        $refMethod = new ReflectionMethod($this, 'dummy');
        $docBlockObj = $docBlockFactory->create($refMethod);

        $this->expectException(CannotMapTypeException::class);
        $this->expectExceptionMessage('For return type of TheCodingMachine\GraphQLite\Mappers\Parameters\TypeMapperTest::dummy, in GraphQL, you can only use union types between objects. These types cannot be used in union types: String!, Int!');
        $typeMapper->mapReturnType($refMethod, $docBlockObj);
    }

    public function testMapObjectUnionWorks(): void
    {
        $docBlockFactory = $this->getDocBlockFactory();

        $typeMapper = new TypeHandler(
            $this->getArgumentResolver(),
            $this->getRootTypeMapper(),
            $this->getTypeResolver(),
            $docBlockFactory,
        );

        $refMethod = new ReflectionMethod(UnionOutputType::class, 'objectUnion');
        $docBlockObj = $docBlockFactory->create($refMethod);

        $gqType = $typeMapper->mapReturnType($refMethod, $docBlockObj);
        $this->assertInstanceOf(NonNull::class, $gqType);
        assert($gqType instanceof NonNull);
        $memberType = $gqType->getWrappedType();
        $this->assertInstanceOf(UnionType::class, $memberType);
        assert($memberType instanceof UnionType);
        $unionTypes = $memberType->getTypes();
        $this->assertEquals('TestObject', $unionTypes[0]->name);
        $this->assertEquals('TestObject2', $unionTypes[1]->name);
    }

    public function testMapObjectNullableUnionWorks(): void
    {
        $docBlockFactory = $this->getDocBlockFactory();

        $typeMapper = new TypeHandler(
            $this->getArgumentResolver(),
            $this->getRootTypeMapper(),
            $this->getTypeResolver(),
            $docBlockFactory,
        );

        $refMethod = new ReflectionMethod(UnionOutputType::class, 'nullableObjectUnion');
        $docBlockObj = $docBlockFactory->create($refMethod);

        $gqType = $typeMapper->mapReturnType($refMethod, $docBlockObj);
        $this->assertNotInstanceOf(NonNull::class, $gqType);
        assert(! ($gqType instanceof NonNull));
        $this->assertInstanceOf(UnionType::class, $gqType);
        assert($gqType instanceof UnionType);
        $unionTypes = $gqType->getTypes();
        $this->assertEquals(2, count($unionTypes));
        $this->assertEquals('TestObject', $unionTypes[0]->name);
        $this->assertEquals('TestObject2', $unionTypes[1]->name);
    }

    public function testHideParameter(): void
    {
        $docBlockFactory = $this->getDocBlockFactory();

        $typeMapper = new TypeHandler(
            $this->getArgumentResolver(),
            $this->getRootTypeMapper(),
            $this->getTypeResolver(),
            $docBlockFactory,
        );

        $refMethod = new ReflectionMethod($this, 'withDefaultValue');
        $refParameter = $refMethod->getParameters()[0];
        $docBlockObj = $docBlockFactory->create($refMethod);
        $annotations = $this->getAnnotationReader()->getParameterAnnotationsPerParameter([$refParameter])['foo'];

        $param = $typeMapper->mapParameter($refParameter, $docBlockObj, null, $annotations);

        $this->assertInstanceOf(DefaultValueParameter::class, $param);

        $resolveInfo = $this->createMock(ResolveInfo::class);
        $this->assertSame(24, $param->resolve(null, [], null, $resolveInfo));
    }

    public function testParameterWithDescription(): void
    {
        $docBlockFactory = $this->getDocBlockFactory();

        $typeMapper = new TypeHandler(
            $this->getArgumentResolver(),
            $this->getRootTypeMapper(),
            $this->getTypeResolver(),
            $docBlockFactory,
        );

        $refMethod = new ReflectionMethod($this, 'withParamDescription');
        $docBlockObj = $docBlockFactory->create($refMethod);
        $refParameter = $refMethod->getParameters()[0];

        $parameter = $typeMapper->mapParameter($refParameter, $docBlockObj, null, $this->getAnnotationReader()->getParameterAnnotationsPerParameter([$refParameter])['foo']);
        $this->assertInstanceOf(InputTypeParameter::class, $parameter);
        assert($parameter instanceof InputTypeParameter);
        $this->assertEquals('Foo parameter', $parameter->getDescription());
    }

    public function testHideParameterException(): void
    {
        $docBlockFactory = $this->getDocBlockFactory();

        $typeMapper = new TypeHandler(
            $this->getArgumentResolver(),
            $this->getRootTypeMapper(),
            $this->getTypeResolver(),
            $docBlockFactory,
        );

        $refMethod = new ReflectionMethod($this, 'withoutDefaultValue');
        $refParameter = $refMethod->getParameters()[0];
        $docBlockObj = $docBlockFactory->create($refMethod);
        $annotations = $this->getAnnotationReader()->getParameterAnnotationsPerParameter([$refParameter])['foo'];

        $this->expectException(CannotHideParameterRuntimeException::class);
        $this->expectExceptionMessage('For parameter $foo of method TheCodingMachine\GraphQLite\Mappers\Parameters\TypeMapperTest::withoutDefaultValue(), cannot use the @HideParameter annotation. The parameter needs to provide a default value.');

        $typeMapper->mapParameter($refParameter, $docBlockObj, null, $annotations);
    }

    private function dummy(): int|string
    {
    }

    /** @param int $foo Foo parameter */
    private function withParamDescription(int $foo): void
    {
    }

    private function withDefaultValue(#[HideParameter]
    $foo = 24,): void
    {
    }

    private function withoutDefaultValue(#[HideParameter]
    $foo,): void
    {
    }
}
